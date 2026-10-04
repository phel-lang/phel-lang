<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use ArrayIterator;
use Generator;
use InvalidArgumentException;
use Iterator;
use Phel\Lang\BigDecimal;
use Phel\Lang\BigInt;
use Phel\Lang\NumericCoercion;
use Phel\Lang\NumericOperations;
use Phel\Lang\Ratio;

use function get_debug_type;
use function is_array;
use function is_float;
use function is_int;
use function is_iterable;
use function is_string;
use function mb_str_split;
use function sprintf;

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
     * @return iterable<mixed>
     */
    public static function toIterable(mixed $value): iterable
    {
        if (is_string($value)) {
            return mb_str_split($value);
        }

        if ($value === null) {
            return [];
        }

        if (is_iterable($value)) {
            return $value;
        }

        throw new InvalidArgumentException(sprintf(
            'cannot iterate over a non-sequable value of type %s',
            get_debug_type($value),
        ));
    }

    /**
     * Converts a value to an Iterator for code that needs controlled advancement.
     *
     * @return Iterator<int|string, mixed>
     */
    public static function toIterator(mixed $value): Iterator
    {
        $iterable = self::toIterable($value);

        if ($iterable instanceof Iterator) {
            return $iterable;
        }

        if (is_array($iterable)) {
            return new ArrayIterator($iterable);
        }

        return (static fn(): Generator => yield from $iterable)();
    }

    /**
     * Yields each value paired with its zero-based index.
     *
     * @return Generator<int, array{0:int, 1:mixed}>
     */
    public static function indexed(mixed $iterable): Generator
    {
        $index = 0;
        foreach (self::toIterable($iterable) as $value) {
            yield [$index, $value];
            ++$index;
        }
    }

    /**
     * Generates a range of numbers [start, end) with given step.
     *
     * Examples:
     *   range(0, 5, 1)     // => [0, 1, 2, 3, 4]
     *   range(0, 10, 2)    // => [0, 2, 4, 6, 8]
     *   range(5, 0, -1)    // => [5, 4, 3, 2, 1]
     *   range(0.0, 1.0, 0.25)  // => [0.0, 0.25, 0.5, 0.75]
     *   range(0, 5/2, 1)   // => [0, 1, 2]
     *
     * Native ints and floats take the plain PHP loop; a `BigInt`, `Ratio` or
     * `BigDecimal` bound goes through the numeric tower.
     *
     * @return Generator<int, BigDecimal|BigInt|float|int|Ratio>
     */
    public static function range(mixed $start, mixed $end, mixed $step): Generator
    {
        if (
            (is_int($start) || is_float($start))
            && (is_int($end) || is_float($end))
            && (is_int($step) || is_float($step))
        ) {
            return self::nativeRange($start, $end, $step);
        }

        NumericCoercion::ensureNumeric($start);
        NumericCoercion::ensureNumeric($end);
        NumericCoercion::ensureNumeric($step);

        return self::tower($start, $end, $step);
    }

    /**
     * @return Generator<int, float|int>
     *
     * @psalm-suppress InvalidOperand
     */
    private static function nativeRange(int|float $start, int|float $end, int|float $step): Generator
    {
        $cmp = $step < 0
            ? static fn(int|float $i, int|float $e): bool => $i > $e
            : static fn(int|float $i, int|float $e): bool => $i < $e;

        for ($i = $start; $cmp($i, $end); $i += $step) {
            yield $i;
        }
    }

    /**
     * @return Generator<int, BigDecimal|BigInt|float|int|Ratio>
     */
    private static function tower(
        BigDecimal|BigInt|float|int|Ratio $start,
        BigDecimal|BigInt|float|int|Ratio $end,
        BigDecimal|BigInt|float|int|Ratio $step,
    ): Generator {
        $direction = NumericOperations::compare($step, 0) < 0 ? -1 : 1;

        for ($i = $start; NumericOperations::compare($i, $end) === -$direction; $i = NumericOperations::add($i, $step)) {
            yield $i;
        }
    }
}
