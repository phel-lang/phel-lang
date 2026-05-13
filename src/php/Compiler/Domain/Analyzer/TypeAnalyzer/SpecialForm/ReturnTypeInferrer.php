<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function count;
use function in_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Conservative tail-position type inference for `fn` bodies. Walks the
 * body's tail expression looking for a primitive PHP operator on
 * already-typed locals or scalar literals, and surfaces the implied
 * PHP type so the emitter can stamp it on the compiled signature.
 *
 * Inference is intentionally narrow: any non-recognized node, an
 * untyped local, or a branch disagreement returns `null`, leaving the
 * fn's return type unset. Explicit `:tag` declarations on the param
 * vector or the def name take precedence; this class only fills the
 * gap when no `:tag` is present.
 */
final class ReturnTypeInferrer
{
    /**
     * Sentinel for nodes that do not produce a runtime value (`recur`,
     * `throw`). Used to merge `if` branches where one side is bottom
     * and the other yields a concrete type.
     */
    private const string BOTTOM = '@bottom';

    /** @var array<string, string> */
    private const array COMPARISON_OPS = [
        '<' => 'bool', '>' => 'bool', '<=' => 'bool', '>=' => 'bool',
        '==' => 'bool', '===' => 'bool', '!=' => 'bool', '!==' => 'bool',
        '<=>' => 'int', 'instanceof' => 'bool',
    ];

    /** @var list<string> */
    private const array NUMERIC_OPS = [
        '+', '-', '*', '/', '%', '**', '<<', '>>', '|', '&', '^',
    ];

    /**
     * Pure PHP built-ins whose return type is fixed regardless of argument
     * type. Limited to side-effect-free functions with a stable signature
     * across supported PHP versions; anything whose return type depends on
     * argument types or runtime mode stays out so the inferrer never
     * stamps the wrong tag.
     *
     * @var array<string, string>
     */
    private const array PURE_PHP_FN_RETURN = [
        'strlen' => 'int',
        'intval' => 'int',
        'mb_strlen' => 'int',
        'count' => 'int',
        'random_int' => 'int',
        'intdiv' => 'int',
        'floatval' => 'float',
        'doubleval' => 'float',
        'floor' => 'float',
        'ceil' => 'float',
        'round' => 'float',
        'boolval' => 'bool',
        'is_int' => 'bool',
        'is_integer' => 'bool',
        'is_long' => 'bool',
        'is_float' => 'bool',
        'is_double' => 'bool',
        'is_string' => 'bool',
        'is_bool' => 'bool',
        'is_null' => 'bool',
        'is_array' => 'bool',
        'is_object' => 'bool',
        'is_callable' => 'bool',
        'is_numeric' => 'bool',
        'strval' => 'string',
        'strtolower' => 'string',
        'strtoupper' => 'string',
        'mb_strtolower' => 'string',
        'mb_strtoupper' => 'string',
        'trim' => 'string',
        'ltrim' => 'string',
        'rtrim' => 'string',
        'sprintf' => 'string',
        'gettype' => 'string',
    ];

    /**
     * Set during a walk when a primitive PHP operator is encountered.
     * Only then does the function publish an inferred return type;
     * pure literal / pass-through bodies stay untyped so the emitter
     * does not synthesize an annotation the user never asked for.
     */
    private bool $sawOperator = false;

    private ?string $selfNamespace = null;

    private ?string $selfName = null;

    /**
     * `$selfNamespace` / `$selfName` short-circuit `:tag` lookup for the
     * def currently being analyzed. The runtime registry can still hold
     * a `:tag` from a previous compile/eval of the same name; treating
     * any self-referencing call as untagged keeps a redefinition from
     * inheriting that stale signal.
     *
     * @param list<Symbol> $params
     *
     * @psalm-suppress RedundantCondition `inferNode` mutates `sawOperator`
     */
    public function infer(
        AbstractNode $body,
        array $params,
        bool $isVariadic = false,
        ?string $selfNamespace = null,
        ?string $selfName = null,
    ): ?string {
        $paramTypes = $this->collectParamTypes($params, $isVariadic);
        $this->sawOperator = false;
        $this->selfNamespace = $selfNamespace;
        $this->selfName = $selfName;
        $type = $this->inferNode($body, $paramTypes);

        if (!$this->sawOperator) {
            return null;
        }

        return $type === self::BOTTOM ? null : $type;
    }

    /**
     * The variadic tail's `:tag` describes the element type, not the
     * value bound in the body (which is a Phel `Vector`). Skip it so
     * the inferrer does not see a fake int/string local where the
     * runtime actually carries a collection.
     *
     * @param list<Symbol> $params
     *
     * @return array<string, string>
     */
    private function collectParamTypes(array $params, bool $isVariadic): array
    {
        $types = [];
        $lastIndex = $isVariadic ? count($params) - 1 : count($params);
        for ($i = 0; $i < $lastIndex; ++$i) {
            $param = $params[$i];
            $tag = $this->extractTag($param);
            if ($tag !== null) {
                $types[$param->getName()] = $tag;
            }
        }

        return $types;
    }

    /**
     * @param array<string, string> $locals
     *
     * @phpstan-impure mutates `sawOperator` when a primitive op fires
     */
    private function inferNode(AbstractNode $node, array $locals): ?string
    {
        return match (true) {
            $node instanceof DoNode => $this->inferDo($node, $locals),
            $node instanceof IfNode => $this->inferIf($node, $locals),
            $node instanceof LetNode => $this->inferLet($node, $locals),
            $node instanceof CallNode => $this->inferCall($node, $locals),
            $node instanceof LiteralNode => $this->inferLiteral($node),
            $node instanceof LocalVarNode => $locals[$node->getName()->getName()] ?? null,
            $node instanceof RecurNode => $this->inferRecur($node, $locals),
            $node instanceof ThrowNode => self::BOTTOM,
            default => null,
        };
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferDo(DoNode $node, array $locals): ?string
    {
        return $this->inferNode($node->getRet(), $locals);
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferIf(IfNode $node, array $locals): ?string
    {
        // Walk the test for its operator side-effect: `(if (php/< x 0) ...)`
        // should mark the inference triggered even though the test's type
        // never reaches the return slot.
        $this->inferNode($node->getTestExpr(), $locals);
        $then = $this->inferNode($node->getThenExpr(), $locals);
        $else = $this->inferNode($node->getElseExpr(), $locals);
        if ($then === null || $else === null) {
            return null;
        }

        if ($then === self::BOTTOM) {
            return $else;
        }

        if ($else === self::BOTTOM) {
            return $then;
        }

        return $then === $else ? $then : null;
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferRecur(RecurNode $node, array $locals): string
    {
        // Recur jumps; it never produces a value. Walk the rebinding
        // expressions so any operator hidden in them still trips the
        // `sawOperator` gate.
        foreach ($node->getExpressions() as $expr) {
            $this->inferNode($expr, $locals);
        }

        return self::BOTTOM;
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferLet(LetNode $node, array $locals): ?string
    {
        $bindings = $node->getBindings();
        $names = [];
        foreach ($bindings as $i => $binding) {
            $name = $this->bindingName($binding);
            $names[$i] = $name;
            $type = $this->inferNode($binding->getInitExpr(), $locals);
            if ($type !== null && $type !== self::BOTTOM) {
                $locals[$name] = $type;
            }
        }

        if ($node->isLoop()) {
            // Loop bindings are rebindable via recur; drop any whose recur
            // argument types disagree with (or are unknown relative to) the
            // initial type, so the body never sees an over-narrow tag.
            $recurTypes = [];
            $this->collectRecurArgTypes($node->getBodyExpr(), $locals, $recurTypes);
            foreach ($names as $i => $name) {
                if (!isset($locals[$name])) {
                    continue;
                }

                $alts = $recurTypes[$i] ?? null;
                if ($alts === null || !in_array($locals[$name], $alts, true) || count($alts) > 1) {
                    unset($locals[$name]);
                }
            }
        }

        return $this->inferNode($node->getBodyExpr(), $locals);
    }

    /**
     * Walks tail-position descendants for `recur` invocations targeting the
     * enclosing loop and records each argument's inferred type per binding
     * index. Nested loops own their own recurs and are skipped.
     *
     * @param array<string, string>     $locals
     * @param array<int, list<?string>> $types
     */
    private function collectRecurArgTypes(AbstractNode $node, array $locals, array &$types): void
    {
        if ($node instanceof RecurNode) {
            foreach ($node->getExpressions() as $i => $expr) {
                $type = $this->inferNode($expr, $locals);
                $types[$i][] = ($type === null || $type === self::BOTTOM) ? null : $type;
            }

            return;
        }

        if ($node instanceof IfNode) {
            $this->collectRecurArgTypes($node->getThenExpr(), $locals, $types);
            $this->collectRecurArgTypes($node->getElseExpr(), $locals, $types);
            return;
        }

        if ($node instanceof DoNode) {
            $this->collectRecurArgTypes($node->getRet(), $locals, $types);
            return;
        }

        if ($node instanceof LetNode && !$node->isLoop()) {
            $this->collectRecurArgTypes($node->getBodyExpr(), $locals, $types);
        }
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferCall(CallNode $node, array $locals): ?string
    {
        $fn = $node->getFn();
        return match (true) {
            $fn instanceof GlobalVarNode => $this->inferGlobalCall($fn),
            $fn instanceof PhpVarNode => $this->inferPhpCall($fn, $node, $locals),
            default => null,
        };
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferPhpCall(PhpVarNode $fn, CallNode $node, array $locals): ?string
    {
        $op = $fn->getName();

        if (isset(self::COMPARISON_OPS[$op])) {
            return $this->publish(self::COMPARISON_OPS[$op]);
        }

        if ($op === '.') {
            return $this->publish('string');
        }

        if (in_array($op, self::NUMERIC_OPS, true)) {
            $this->sawOperator = true;
            return $this->inferNumeric($node, $locals);
        }

        if (isset(self::PURE_PHP_FN_RETURN[$op])) {
            return $this->publish(self::PURE_PHP_FN_RETURN[$op]);
        }

        return null;
    }

    /**
     * Cross-fn propagation: when the tail call resolves to another
     * global definition, trust its `:tag` meta as the inferred return
     * type. The callee's tag is either user-declared or already filled
     * by this inferrer on a previous pass; either way it represents the
     * same contract the call site is bound to honour, so propagating it
     * cannot disagree with what the callee actually returns.
     */
    private function inferGlobalCall(GlobalVarNode $fn): ?string
    {
        if ($this->selfNamespace !== null
            && $this->selfName !== null
            && $fn->getNamespace() === $this->selfNamespace
            && $fn->getName()->getName() === $this->selfName
        ) {
            return null;
        }

        $tag = $this->tagFromMeta($fn->getMeta());
        return $tag === null ? null : $this->publish($tag);
    }

    private function publish(string $type): string
    {
        $this->sawOperator = true;
        return $type;
    }

    /**
     * @param array<string, string> $locals
     */
    private function inferNumeric(CallNode $node, array $locals): ?string
    {
        $hasFloat = false;
        foreach ($node->getArguments() as $arg) {
            $type = $this->inferNode($arg, $locals);
            if ($type === null || $type === self::BOTTOM) {
                return null;
            }

            if ($type === 'float') {
                $hasFloat = true;
                continue;
            }

            if ($type !== 'int') {
                return null;
            }
        }

        return $hasFloat ? 'float' : 'int';
    }

    private function inferLiteral(LiteralNode $node): ?string
    {
        $value = $node->getValue();
        return match (true) {
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            is_string($value) => 'string',
            default => null,
        };
    }

    private function extractTag(Symbol $param): ?string
    {
        $meta = $param->getMeta();
        return $meta instanceof PersistentMapInterface ? $this->tagFromMeta($meta) : null;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    private function tagFromMeta(PersistentMapInterface $meta): ?string
    {
        $tag = $meta->find(Keyword::create('tag'));
        if ($tag instanceof Symbol) {
            return $tag->getName();
        }

        return is_string($tag) && $tag !== '' ? $tag : null;
    }

    private function bindingName(BindingNode $binding): string
    {
        return $binding->getShadow()->getName();
    }
}
