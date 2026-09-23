<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Closure;
use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Lang\Keyword;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

use function array_all;
use function array_any;
use function in_array;
use function is_object;
use function str_replace;

/**
 * Whether a literal `fn` body may run spliced into the enclosing PHP frame
 * instead of in its own closure ({@see UpdateLiteralFnLowering}).
 *
 * An allowlist, not a denylist: every node of the body must be one of the
 * shapes below, or the closure stays. A closure has its own frame and holds
 * copies of the enclosing locals, so anything that writes a variable, reaches
 * one by reference, or reads the frame itself (`func_get_args`, `extract`,
 * `compact`) could tell the two apart. The shapes here can do none of that:
 *
 * - literals, quoted forms, local and global reads;
 * - vector, map and set literals without reader metadata, `php/aget`;
 * - `if`, `do` and `let` (not `loop`), which `and`, `or`, `when` and `cond`
 *   expand to;
 * - a nested `fn`, which runs in a frame of its own and captures by value
 *   either way, so its body is not walked;
 * - calls whose callee is a PHP operator other than `=` and `=&`, one of
 *   {@see self::PURE_PHP_FUNCTIONS}, a keyword, or a global Phel fn with no
 *   `^:by-ref` param that is neither `^:dynamic` nor `^:redef`.
 *
 * A `let` binding or `if` test whose value may be an object the body built
 * keeps the closure too: the closure let go of it before `assoc` ran, and
 * the caller's frame holds it until after ({@see self::mayBeFreshObject()}).
 *
 * Anything else, including every other PHP function, method calls,
 * `php/new`, the `php/aset` family, `php/ref`, `try`, `loop`, `foreach` and
 * calls through a local, keeps the closure.
 *
 * @internal
 */
final readonly class SpliceableBody
{
    /**
     * PHP builtins that take every argument by value and never look at the
     * calling frame.
     */
    private const array PURE_PHP_FUNCTIONS = [
        'abs', 'boolval', 'ceil', 'count', 'floatval', 'floor', 'fmod', 'intdiv', 'intval',
        'is_array', 'is_bool', 'is_float', 'is_int', 'is_null', 'is_numeric', 'is_string',
        'max', 'min', 'round', 'sqrt', 'strlen', 'strtolower', 'strtoupper', 'strval',
    ];

    private const array WRITING_OPERATORS = ['=', '=&'];

    private const array SCALAR_TYPES = ['int', 'float', 'string', 'bool', 'true', 'false', 'null', 'void'];

    public function isSpliceable(AbstractNode $node): bool
    {
        return match (true) {
            $node instanceof LiteralNode,
            $node instanceof QuoteNode,
            $node instanceof LocalVarNode,
            $node instanceof GlobalVarNode,
            $node instanceof FnNode,
            $node instanceof MultiFnNode => true,
            // Reader metadata on a collection literal is evaluated too, and
            // nothing here walks it.
            $node instanceof VectorNode => !$node->getMeta() instanceof MapNode && $this->all($node->getArgs()),
            $node instanceof MapNode => !$node->getLiteralMeta() instanceof MapNode && $this->all($node->getKeyValues()),
            $node instanceof SetNode => !$node->getMeta() instanceof MapNode && $this->all($node->getValues()),
            $node instanceof PhpArrayGetNode => $this->all([$node->getArrayExpr(), ...$node->getAccessExprs()]),
            $node instanceof IfNode => !$this->mayBeFreshObject($node->getTestExpr())
                && $this->all([$node->getTestExpr(), $node->getThenExpr(), $node->getElseExpr()]),
            $node instanceof DoNode => $this->all([...$node->getStmts(), $node->getRet()]),
            $node instanceof LetNode => !$node->isLoop()
                && !array_any($this->initExprs($node), $this->mayBeFreshObject(...))
                && $this->all([...$this->initExprs($node), $node->getBodyExpr()]),
            $node instanceof CallNode => $this->isSafeCallee($node->getFn()) && $this->all($node->getArguments()),
            default => false,
        };
    }

    /**
     * Whether the value may be an object the body created and nothing
     * outside it holds, which a variable of the body keeps alive.
     *
     * The closure released its locals when it returned, before `assoc` ran.
     * Spliced, a `let` binding and the variable an `if` test is stored in
     * (`$__truthy`, `$__or` for `and` and `or`) live in the caller's frame
     * until it returns, after `assoc`: a destructor would run later, and a
     * target's `assoc` could see the object alive. Only user code can build
     * such an object, so a Phel fn or keyword call counts as one unless the
     * fn declares a scalar return type. The param is not a concern: the
     * runtime `update` binds the current value too, and holds it through
     * its `assoc` as well.
     */
    private function mayBeFreshObject(AbstractNode $node): bool
    {
        return match (true) {
            $node instanceof LiteralNode,
            $node instanceof QuoteNode,
            $node instanceof LocalVarNode,
            $node instanceof GlobalVarNode,
            $node instanceof FnNode,
            $node instanceof MultiFnNode => false,
            $node instanceof VectorNode => $this->any($node->getArgs()),
            $node instanceof MapNode => $this->any($node->getKeyValues()),
            $node instanceof SetNode => $this->any($node->getValues()),
            $node instanceof PhpArrayGetNode => $this->mayBeFreshObject($node->getArrayExpr()),
            $node instanceof IfNode => $this->any([$node->getThenExpr(), $node->getElseExpr()]),
            $node instanceof DoNode => $this->mayBeFreshObject($node->getRet()),
            $node instanceof LetNode => $this->mayBeFreshObject($node->getBodyExpr()),
            // `max` and `min` answer one of their arguments, and `+` joins
            // two arrays, so the operands decide.
            $node instanceof CallNode && $node->getFn() instanceof PhpVarNode => $this->any($node->getArguments()),
            $node instanceof CallNode && $node->getFn() instanceof GlobalVarNode => !$this->returnsAScalar($node->getFn()),
            default => true,
        };
    }

    /**
     * @return list<AbstractNode>
     */
    private function initExprs(LetNode $node): array
    {
        return array_map(static fn(BindingNode $b): AbstractNode => $b->getInitExpr(), $node->getBindings());
    }

    /**
     * @param array<int, AbstractNode> $nodes
     */
    private function any(array $nodes): bool
    {
        return array_any($nodes, $this->mayBeFreshObject(...));
    }

    /**
     * @param array<int, AbstractNode> $nodes
     */
    private function all(array $nodes): bool
    {
        return array_all($nodes, $this->isSpliceable(...));
    }

    private function isSafeCallee(AbstractNode $fn): bool
    {
        if ($fn instanceof PhpVarNode) {
            return $fn->isInfix()
                ? !in_array($fn->getName(), self::WRITING_OPERATORS, true)
                : in_array($fn->getName(), self::PURE_PHP_FUNCTIONS, true);
        }

        if ($fn instanceof LiteralNode) {
            return $fn->getValue() instanceof Keyword;
        }

        return $fn instanceof GlobalVarNode && !$this->isRebindable($fn) && !$this->takesAReference($fn);
    }

    /**
     * A `^:dynamic` or `^:redef` global may be a different fn by the time
     * the call runs (`binding`, `with-redefs`), so the one reflected here
     * proves nothing about it.
     */
    private function isRebindable(GlobalVarNode $fn): bool
    {
        $meta = $fn->getMeta();

        return (bool) $meta[Keyword::create('dynamic')] || (bool) $meta[Keyword::create('redef')];
    }

    /**
     * A `^:by-ref` param compiles to a PHP `&$param`, so the global's own
     * `__invoke` says whether it can write through an argument. A global
     * that is not defined yet cannot be checked, and counts as one that can.
     */
    private function takesAReference(GlobalVarNode $fn): bool
    {
        $reflection = $this->reflect($fn);

        return !$reflection instanceof ReflectionFunctionAbstract || $this->anyByReference($reflection);
    }

    /**
     * PHP enforces a declared return type, so a scalar one proves the call
     * answers no object. A global that is not defined yet proves nothing.
     */
    private function returnsAScalar(GlobalVarNode $fn): bool
    {
        $type = $this->reflect($fn)?->getReturnType();
        $types = match (true) {
            $type instanceof ReflectionNamedType => [$type],
            $type instanceof ReflectionUnionType => $type->getTypes(),
            default => [],
        };

        return $types !== [] && array_all(
            $types,
            static fn(ReflectionType $t): bool => $t instanceof ReflectionNamedType && in_array($t->getName(), self::SCALAR_TYPES, true),
        );
    }

    private function reflect(GlobalVarNode $fn): ?ReflectionFunctionAbstract
    {
        $value = Phel::getDefinition(str_replace('-', '_', $fn->getNamespace()), $fn->getName()->getName());
        if (!is_object($value)) {
            return null;
        }

        try {
            return $value instanceof Closure
                ? new ReflectionFunction($value)
                : new ReflectionMethod($value, '__invoke');
        } catch (ReflectionException) {
            return null;
        }
    }

    private function anyByReference(ReflectionFunctionAbstract $reflection): bool
    {
        return array_any($reflection->getParameters(), static fn(ReflectionParameter $p): bool => $p->isPassedByReference());
    }
}
