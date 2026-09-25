<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\Cache;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\CatchNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Ast\TryNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\AssocInSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitter\GetInSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitter\GlobalCallTarget;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\IfChainMatchLowerer;
use Phel\Lang\Keyword;

use function array_slice;

/**
 * Walks a fn body looking for *outermost* pure collection literals plus
 * `Keyword` literals so the emitter can hoist them to a per-fn `static`
 * cache, and reserves a per-fn `static $__phel_call_N` slot for each
 * global-fn call site when call-site caching is enabled. Stops at nested
 * function boundaries (FnNode / MultiFnNode / ReifyNode method bodies)
 * because each has its own static scope.
 *
 * @internal
 */
final readonly class BodyConstantScanner
{
    public function scan(AbstractNode $body, ConstantScope $scope, bool $cacheCalls = false): void
    {
        $this->walk($body, $scope, $cacheCalls);
    }

    private function walk(AbstractNode $node, ConstantScope $scope, bool $cacheCalls): void
    {
        // Nested function bodies own their own scope; skip.
        if ($node instanceof FnNode || $node instanceof MultiFnNode || $node instanceof ReifyNode) {
            return;
        }

        if ($node instanceof CallNode && $this->scanLiteralPathCall($node, $scope, $cacheCalls)) {
            return;
        }

        if ($this->isCacheableCollection($node) || $this->isCacheableKeyword($node)) {
            // `LiteralEmitter` writes nothing for a literal in statement
            // position (its value is discarded), so a slot there would be an
            // orphan `static` declaration. Collections are still written.
            if (!$node instanceof LiteralNode || !$node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
                $scope->reserve($node);
            }

            return;
        }

        // When a `LetNode` / `IfNode` will be lowered to a PHP `match`,
        // its cond-test calls and arm literals are consumed by the lowerer
        // (the arms are emitted from the analysed values, not from nodes).
        // Walking them would leave orphan `$__phel_call_N` /
        // `$__phel_const_N` declarations in the generated PHP. A keyword
        // arm value still gets a slot, reserved by value: PHP evaluates the
        // arm keys of a `match` in order until one matches, so an inline
        // `Keyword::create()` would re-intern every missed key per dispatch.
        $shape = $this->matchLoweredShape($node);
        if ($shape !== null) {
            $this->walk($shape['init'], $scope, $cacheCalls);
            foreach ($shape['arms'] as $arm) {
                $this->reserveKeywordValue($arm['key'], $scope);
                $this->reserveKeywordValue($arm['expr'], $scope);
            }

            $this->reserveKeywordValue($shape['fallback'], $scope);
            return;
        }

        if ($cacheCalls
            && $node instanceof CallNode
            && GlobalCallTarget::isCacheableGlobalFnCall($node)
            && !CallSpecialization::isSpecialized($node)
        ) {
            $scope->reserveCallSlot($node);
            // Fall through so child args are still scanned for nested literals/calls.
        }

        foreach ($this->children($node) as $child) {
            $this->walk($child, $scope, $cacheCalls);
        }
    }

    /**
     * A specialised `(get-in coll [k1 k2 …])`, `assoc-in` or `update-in`
     * never emits its path vector. On a tagged `get-in` target the keys
     * become subscripts, so each key is scanned on its own and still hoists
     * where eligible. Otherwise the keys become the PHP array handed to
     * `GetIn::path` or `AssocIn`, which is hoisted whole when every key is a
     * literal, under a slot of its own: a vector literal with the same
     * elements must not share it (#3320, #3328).
     */
    private function scanLiteralPathCall(CallNode $node, ConstantScope $scope, bool $cacheCalls): bool
    {
        $keys = GetInSpecialization::literalPathKeys($node) ?? AssocInSpecialization::literalPathKeys($node);
        if ($keys === null) {
            return false;
        }

        $args = $node->getArguments();
        $this->walk($args[0], $scope, $cacheCalls);

        $path = $args[1];
        if (GetInSpecialization::subscriptChainKeys($node) === null
            && $path instanceof VectorNode
            && $this->isCacheableCollection($path)
        ) {
            $scope->reserveAsPhpArray($path);
        } else {
            foreach ($keys as $key) {
                $this->walk($key, $scope, $cacheCalls);
            }
        }

        foreach (array_slice($args, 2) as $arg) {
            $this->walk($arg, $scope, $cacheCalls);
        }

        return true;
    }

    /**
     * The lowered shape when `$node` will be emitted as a PHP `match` by
     * `\Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\IfChainMatchLowerer`.
     * Only its matched init value still flows through the normal emit path;
     * the cond tests, arm keys and arm bodies are written from values.
     *
     * @return array{init: AbstractNode, arms: list<array{key: mixed, expr: mixed}>, fallback: mixed}|null
     */
    private function matchLoweredShape(AbstractNode $node): ?array
    {
        // Same gate as the emitters' `tryEmitAsMatch()`: a chain whose value
        // is discarded keeps the if/else path, which emits its own nodes.
        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            return null;
        }

        if ($node instanceof LetNode) {
            return IfChainMatchLowerer::analyse($node);
        }

        if ($node instanceof IfNode) {
            return IfChainMatchLowerer::analyseIfChain($node);
        }

        return null;
    }

    private function reserveKeywordValue(mixed $value, ConstantScope $scope): void
    {
        if ($value instanceof Keyword) {
            $scope->reserveKeyword($value);
        }
    }

    private function isCacheableCollection(AbstractNode $node): bool
    {
        if (!$node instanceof VectorNode && !$node instanceof MapNode && !$node instanceof SetNode) {
            return false;
        }

        // Skip empty literals: \Phel::vector([]) already returns an empty
        // singleton via the type factory, so caching adds no win.
        if ($this->isEmpty($node)) {
            return false;
        }

        return PureLiteralDetector::isPure($node);
    }

    /**
     * A `LiteralNode` whose value is a {@see Keyword} is identity-shared
     * via the interpreter's intern pool, but every call site still hits
     * `\Phel::keyword("…")` afresh. Caching the resolved instance in a
     * per-fn `static` slot skips the intern-pool hash on subsequent calls.
     */
    private function isCacheableKeyword(AbstractNode $node): bool
    {
        return $node instanceof LiteralNode && $node->getValue() instanceof Keyword;
    }

    private function isEmpty(AbstractNode $node): bool
    {
        return match (true) {
            $node instanceof VectorNode => $node->getArgs() === [],
            $node instanceof MapNode => $node->getKeyValues() === [],
            $node instanceof SetNode => $node->getValues() === [],
            default => false,
        };
    }

    /**
     * Manual node-type registry. Any AST node added in the future that can
     * contain a child collection literal must be listed here, otherwise the
     * scanner silently produces no children for it and literals nested in
     * the new node will never be hoisted (safe miss, not wrong code).
     * Auditing the {@see \Phel\Compiler\Domain\Analyzer\Ast} namespace when
     * introducing a new node type keeps this list current.
     *
     * @return array<int, AbstractNode>
     */
    private function children(AbstractNode $node): array
    {
        return match (true) {
            $node instanceof CallNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof ApplyNode => [$node->getFn(), ...$node->getArguments()],
            $node instanceof IfNode => [$node->getTestExpr(), $node->getThenExpr(), $node->getElseExpr()],
            $node instanceof DoNode => [...$node->getStmts(), $node->getRet()],
            $node instanceof LetNode => $this->letChildren($node),
            $node instanceof RecurNode => $node->getExpressions(),
            $node instanceof TryNode => $this->tryChildren($node),
            $node instanceof CatchNode => [$node->getBody()],
            $node instanceof ThrowNode => [$node->getExceptionExpr()],
            $node instanceof VectorNode => $node->getArgs(),
            $node instanceof MapNode => $node->getKeyValues(),
            $node instanceof SetNode => $node->getValues(),
            $node instanceof DefNode => [$node->getMeta(), $node->getInit()],
            $node instanceof SetVarNode => [$node->getSymbol(), $node->getValueExpr()],
            $node instanceof ForeachNode => [$node->getListExpr(), $node->getBodyExpr()],
            $node instanceof PhpNewNode => [$node->getClassExpr(), ...$node->getArgs()],
            $node instanceof PhpObjectCallNode => [$node->getTargetExpr(), $node->getCallExpr()],
            $node instanceof PhpObjectSetNode => [$node->getLeftExpr(), $node->getRightExpr()],
            $node instanceof PhpArrayGetNode => [$node->getArrayExpr(), ...$node->getAccessExprs()],
            $node instanceof PhpArraySetNode => [$node->getArrayExpr(), ...$node->getAccessExprs(), $node->getValueExpr()],
            $node instanceof PhpArrayPushNode => [$node->getArrayExpr(), ...$node->getAccessExprs(), $node->getValueExpr()],
            $node instanceof PhpArrayUnsetNode => [$node->getArrayExpr(), ...$node->getAccessExprs()],
            default => [],
        };
    }

    /**
     * @return list<AbstractNode>
     */
    private function letChildren(LetNode $node): array
    {
        $children = [];
        foreach ($node->getBindings() as $binding) {
            $children[] = $binding->getInitExpr();
        }

        $children[] = $node->getBodyExpr();
        return $children;
    }

    /**
     * @return list<AbstractNode>
     */
    private function tryChildren(TryNode $node): array
    {
        $children = [$node->getBody(), ...$node->getCatches()];
        $finally = $node->getFinally();
        if ($finally instanceof AbstractNode) {
            $children[] = $finally;
        }

        return $children;
    }
}
