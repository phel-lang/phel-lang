<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use Generator;

use function is_string;
use function mb_str_split;

/**
 * Shared sequence utilities that do not belong to any specific operation family.
 *
 * Family-specific generators live alongside this class:
 *   - {@see TransformGenerator} — map, filter, keep, mapcat, ...
 *   - {@see SliceGenerator}     — take, drop, takeWhile, dropWhile, ...
 *   - {@see CombineGenerator}   — concat, interleave, interpose, mapMulti
 *   - {@see DedupeGenerator}    — distinct, dedupe, compact
 */
final class SequenceGenerator
{
    /**
     * Converts a value to an iterable for use with foreach.
     * Strings are split into an array of characters using mb_str_split.
     * Other values are returned as-is (or empty array if null).
     *
     * @template T
     *
     * @param iterable<T>|string|null $value
     *
     * @return iterable<string|T>
     */
    public static function toIterable(mixed $value): iterable
    {
        if (is_string($value)) {
            return mb_str_split($value);
        }

        return $value ?? [];
    }

    /**
     * Generates a range of numbers [start, end) with given step.
     *
     * Examples:
     *   range(0, 5, 1)     // => [0, 1, 2, 3, 4]
     *   range(0, 10, 2)    // => [0, 2, 4, 6, 8]
     *   range(5, 0, -1)    // => [5, 4, 3, 2, 1]
     *   range(0.0, 1.0, 0.25)  // => [0.0, 0.25, 0.5, 0.75]
     *
     * @return Generator<int, float|int>
     *
     * @psalm-suppress InvalidOperand
     */
    public static function range(int|float $start, int|float $end, int|float $step): Generator
    {
        $cmp = $step < 0
            ? static fn(int|float $i, int|float $e): bool => $i > $e
            : static fn(int|float $i, int|float $e): bool => $i < $e;

        for ($i = $start; $cmp($i, $end); $i += $step) {
            yield $i;
        }
    }
}
