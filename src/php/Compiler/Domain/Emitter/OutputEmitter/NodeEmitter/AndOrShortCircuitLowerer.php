<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;

use function count;

/**
 * Detects the shape produced by the `(or …)` and `(and …)` macros after
 * expansion. Each operand pushes another `(let [v expr] (if v …))`
 * around the residual chain — so a 3-argument `(or a b c)` becomes
 *
 *     (let [v1 a] (if v1 v1 (let [v2 b] (if v2 v2 c))))
 *
 * and 3-argument `(and a b c)` is the mirror image, with the chain
 * continuation as the `then` branch and the binding as the `else`.
 *
 * Returns a flat list of operand `AbstractNode`s in argument order, or
 * `null` when the input does not match the shape, or when any argument
 * is not a "simple" expression shape — emitting a `LetNode` or `IfNode`
 * as a chain leaf in `IfEmitter` would require swapping its analyser
 * env to `EXPRESSION`, which the AST is intentionally immutable about.
 *
 * Lowering only runs when the chain is consumed in test position
 * (`IfEmitter`); native PHP `||` / `&&` collapse to a bool, so the
 * value-semantics of the Phel forms (returning the first truthy / last
 * falsy *value*, not a bool) would be broken in expression position.
 */
final class AndOrShortCircuitLowerer
{
    private const int SHAPE_OR = 1;

    private const int SHAPE_AND = 2;

    /**
     * @return list<AbstractNode>|null
     */
    public static function extractOrChain(AbstractNode $node): ?array
    {
        return self::walk($node, self::SHAPE_OR);
    }

    /**
     * @return list<AbstractNode>|null
     */
    public static function extractAndChain(AbstractNode $node): ?array
    {
        return self::walk($node, self::SHAPE_AND);
    }

    /**
     * @return list<AbstractNode>|null
     */
    private static function walk(AbstractNode $node, int $shape): ?array
    {
        $args = self::collect($node, $shape);
        if ($args === null) {
            return null;
        }

        foreach ($args as $arg) {
            if (!SimpleExpressionNode::is($arg, false)) {
                return null;
            }
        }

        return $args;
    }

    /**
     * @return list<AbstractNode>|null
     */
    private static function collect(AbstractNode $node, int $shape): ?array
    {
        if (!$node instanceof LetNode) {
            return null;
        }

        $bindings = $node->getBindings();
        if (count($bindings) !== 1) {
            return null;
        }

        $binding = $bindings[0];

        $body = $node->getBodyExpr();
        if ($body instanceof DoNode && $body->getStmts() === []) {
            $body = $body->getRet();
        }

        if (!$body instanceof IfNode) {
            return null;
        }

        if (!self::referencesBinding($body->getTestExpr(), $binding)) {
            return null;
        }

        $shadowExpr = $shape === self::SHAPE_OR ? $body->getThenExpr() : $body->getElseExpr();
        if (!self::referencesBinding($shadowExpr, $binding)) {
            return null;
        }

        $continuation = $shape === self::SHAPE_OR ? $body->getElseExpr() : $body->getThenExpr();
        if ($continuation instanceof DoNode && $continuation->getStmts() === []) {
            $continuation = $continuation->getRet();
        }

        $rest = self::collect($continuation, $shape);
        if ($rest === null) {
            $rest = [$continuation];
        }

        return [$binding->getInitExpr(), ...$rest];
    }

    private static function referencesBinding(AbstractNode $node, BindingNode $binding): bool
    {
        if (!$node instanceof LocalVarNode) {
            return false;
        }

        $name = $node->getName()->getName();
        if ($name === $binding->getShadow()->getName()) {
            return true;
        }

        return $name === $binding->getSymbol()->getName();
    }
}
