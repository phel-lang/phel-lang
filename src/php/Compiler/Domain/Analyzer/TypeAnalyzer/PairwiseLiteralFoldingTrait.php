<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use function count;

/**
 * Shared helpers for the literal comparison folders. Phel's `=`/`not=`/`<`/…
 * are n-ary and fold the same way: an empty arity is left for the runtime
 * (Phel raises), a single operand is `true`, and otherwise every adjacent
 * pair must satisfy the comparator. {@see LiteralStringFolder} and
 * {@see LiteralArithmeticFolder} both reduced over this shape and both
 * negated for `not=`; this keeps that logic in one place.
 */
trait PairwiseLiteralFoldingTrait
{
    /**
     * Fold an n-ary chain of pairwise comparisons to a single bool, or `null`
     * for an empty arity. Returns `false` on the first adjacent pair the
     * comparator rejects, `true` when every pair (or a lone operand) passes.
     *
     * @param list<mixed>                  $literals
     * @param callable(mixed, mixed): bool $compare
     */
    private function foldPairwise(array $literals, callable $compare): ?bool
    {
        $count = count($literals);
        if ($count === 0) {
            return null;
        }

        for ($i = 0; $i < $count - 1; ++$i) {
            if (!$compare($literals[$i], $literals[$i + 1])) {
                return false;
            }
        }

        return true;
    }

    private function negate(?bool $value): ?bool
    {
        return $value === null ? null : !$value;
    }
}
