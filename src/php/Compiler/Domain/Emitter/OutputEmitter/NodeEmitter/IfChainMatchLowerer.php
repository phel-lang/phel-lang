<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Lang\Keyword;
use Phel\Shared\CompilerConstants;

use function count;
use function in_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * Detects the post-macroexpansion shape of a `case` / `cond`:
 *
 *   (let [g1 e]
 *     (if (= g1 lit1) lit-then1
 *       (let [g2 g1]
 *         (if (= g2 lit2) lit-then2
 *           ... lit-fallback))))
 *
 * where every test compares a (transitively rebound) shadow of the
 * outer let's binding against a primitive literal. Phel's `case`
 * macro is recursive and wraps each test in its own `let`, so the
 * walker threads the active shadow through both `LetNode` and
 * `IfNode` layers.
 *
 * Arm bodies and the fallback must reduce to primitive literal
 * values (`LiteralNode` or `QuoteNode` wrapping an int / string /
 * bool / Keyword) so the emitter can render them via `emitLiteral`
 * directly — bypassing the context-prefix machinery that would
 * inject `return …;` inside a match arm and break PHP syntax.
 */
final readonly class IfChainMatchLowerer
{
    /** @var list<string> */
    private const array EQUALITY_FNS = ['=', 'equals1'];

    /**
     * Sentinel: returning `null` would collide with the legitimate
     * `LiteralNode(null)` value (Phel's `nil`).
     */
    private const string NOT_FOLDABLE = "\0NOT_FOLDABLE\0";

    /**
     * @return array{init: AbstractNode, arms: list<array{key: mixed, expr: mixed}>, fallback: mixed}|null
     */
    public static function analyse(LetNode $root): ?array
    {
        $bindings = $root->getBindings();
        if ($root->isLoop() || count($bindings) !== 1) {
            return null;
        }

        $binding = $bindings[0];
        $body = $root->getBodyExpr();
        if (!$body instanceof DoNode || $body->getStmts() !== []) {
            return null;
        }

        $activeShadow = $binding->getShadow()->getName();
        $current = $body->getRet();

        $arms = [];
        while (true) {
            if ($current instanceof IfNode) {
                $key = self::matchEqualityTest($current->getTestExpr(), $activeShadow);
                if ($key === self::NOT_FOLDABLE) {
                    return null;
                }

                $then = self::literalValue($current->getThenExpr());
                if ($then === self::NOT_FOLDABLE) {
                    return null;
                }

                $arms[] = ['key' => $key, 'expr' => $then];
                $current = $current->getElseExpr();
                continue;
            }

            if ($current instanceof LetNode && !$current->isLoop()) {
                $innerBindings = $current->getBindings();
                if (count($innerBindings) !== 1) {
                    break;
                }

                $innerBinding = $innerBindings[0];
                $innerInit = $innerBinding->getInitExpr();
                if (!$innerInit instanceof LocalVarNode
                    || $innerInit->getName()->getName() !== $activeShadow
                ) {
                    break;
                }

                $activeShadow = $innerBinding->getShadow()->getName();

                $innerBody = $current->getBodyExpr();
                if (!$innerBody instanceof DoNode || $innerBody->getStmts() !== []) {
                    break;
                }

                $current = $innerBody->getRet();
                continue;
            }

            break;
        }

        $fallback = self::literalValue($current);
        if (count($arms) < 2 || $fallback === self::NOT_FOLDABLE) {
            return null;
        }

        return [
            'init' => $binding->getInitExpr(),
            'arms' => $arms,
            'fallback' => $fallback,
        ];
    }

    private static function literalValue(AbstractNode $node): mixed
    {
        if ($node instanceof LiteralNode) {
            $value = $node->getValue();
            return self::isPrimitive($value) ? $value : self::NOT_FOLDABLE;
        }

        if ($node instanceof QuoteNode) {
            $value = $node->getValue();
            return self::isPrimitive($value) ? $value : self::NOT_FOLDABLE;
        }

        return self::NOT_FOLDABLE;
    }

    private static function matchEqualityTest(AbstractNode $test, string $activeShadow): mixed
    {
        if (!$test instanceof CallNode) {
            return self::NOT_FOLDABLE;
        }

        $fn = $test->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return self::NOT_FOLDABLE;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || !in_array($fn->getName()->getName(), self::EQUALITY_FNS, true)
        ) {
            return self::NOT_FOLDABLE;
        }

        $args = $test->getArguments();
        if (count($args) !== 2) {
            return self::NOT_FOLDABLE;
        }

        [$lhs, $rhs] = $args;

        if ($lhs instanceof LocalVarNode && $lhs->getName()->getName() === $activeShadow) {
            return self::literalValue($rhs);
        }

        if ($rhs instanceof LocalVarNode && $rhs->getName()->getName() === $activeShadow) {
            return self::literalValue($lhs);
        }

        return self::NOT_FOLDABLE;
    }

    private static function isPrimitive(mixed $value): bool
    {
        return is_int($value)
            || is_string($value)
            || is_bool($value)
            || $value instanceof Keyword;
    }
}
