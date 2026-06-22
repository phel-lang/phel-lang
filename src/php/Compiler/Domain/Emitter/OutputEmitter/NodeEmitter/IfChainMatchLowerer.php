<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhelCoreCall;
use Phel\Lang\Keyword;

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
     * Detects the lone-`if`-chain shape produced by `(cond (= x lit) e …)`
     * with no outer `let`. Every test must reference the **same**
     * `LocalVarNode` shadow.
     *
     * @return array{init: LocalVarNode, arms: list<array{key: mixed, expr: mixed}>, fallback: mixed}|null
     */
    public static function analyseIfChain(IfNode $root): ?array
    {
        $arms = [];
        $shared = null;
        $current = $root;

        while ($current instanceof IfNode) {
            $info = self::parseEqualityCall($current->getTestExpr());
            if ($info === null) {
                return null;
            }

            [$candidate, $key] = $info;

            if ($shared === null) {
                $shared = $candidate;
            } elseif ($shared->getName()->getName() !== $candidate->getName()->getName()) {
                return null;
            }

            $then = self::literalValue($current->getThenExpr());
            if ($then === self::NOT_FOLDABLE) {
                return null;
            }

            $arms[] = ['key' => $key, 'expr' => $then];
            $current = $current->getElseExpr();
        }

        $fallback = self::literalValue($current);
        // `count($arms) >= 2` guarantees the loop ran at least once
        // and `$shared` was assigned during the first iteration.
        if (count($arms) < 2 || $fallback === self::NOT_FOLDABLE) {
            return null;
        }

        return [
            'init' => $shared,
            'arms' => $arms,
            'fallback' => $fallback,
        ];
    }

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

    /**
     * Parse a `(= local lit)` / `(= lit local)` equality test into its
     * (local-var, folded-literal) pair, or `null` when the call is not an
     * eligible equality of a local against a primitive literal.
     *
     * @return array{0: LocalVarNode, 1: mixed}|null
     */
    private static function parseEqualityCall(AbstractNode $test): ?array
    {
        if (!$test instanceof CallNode) {
            return null;
        }

        $name = PhelCoreCall::nameOf($test);
        if ($name === null || !in_array($name, self::EQUALITY_FNS, true)) {
            return null;
        }

        $args = $test->getArguments();
        if (count($args) !== 2) {
            return null;
        }

        [$lhs, $rhs] = $args;

        if ($lhs instanceof LocalVarNode) {
            $value = self::literalValue($rhs);
            return $value === self::NOT_FOLDABLE ? null : [$lhs, $value];
        }

        if ($rhs instanceof LocalVarNode) {
            $value = self::literalValue($lhs);
            return $value === self::NOT_FOLDABLE ? null : [$rhs, $value];
        }

        return null;
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
        $parsed = self::parseEqualityCall($test);
        if ($parsed === null) {
            return self::NOT_FOLDABLE;
        }

        [$local, $value] = $parsed;

        return $local->getName()->getName() === $activeShadow ? $value : self::NOT_FOLDABLE;
    }

    private static function isPrimitive(mixed $value): bool
    {
        return is_int($value)
            || is_string($value)
            || is_bool($value)
            || $value instanceof Keyword;
    }
}
