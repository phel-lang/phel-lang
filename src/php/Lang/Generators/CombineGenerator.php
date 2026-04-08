<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use ArrayIterator;
use Generator;
use Iterator;

use function is_array;
use function is_string;
use function mb_str_split;

/**
 * Multi-source and separator-aware combining generators.
 *
 * Each operation orchestrates one or more input sequences to produce a combined
 * output — concatenation, round-robin interleaving, element-wise zipping, and
 * separator insertion. The private helpers normalize arbitrary iterables into
 * Iterator instances so advancement can be controlled step by step.
 */
final class CombineGenerator
{
    /**
     * Concatenates multiple iterables into a single lazy sequence.
     * Yields all elements from the first iterable, then all from the second, etc.
     *
     * IMPORTANT: Null arguments are skipped (they contribute nothing to concatenation).
     * This matches Clojure semantics where (concat [1 2] nil [3]) => (1 2 3).
     * However, null VALUES inside collections are preserved.
     *
     * Examples:
     *   concat([1, 2], [3, 4])        // => [1, 2, 3, 4]
     *   concat([1, 2], null, [3, 4])  // => [1, 2, 3, 4]  - null arg skipped
     *   concat([1, null, 2], [3])     // => [1, null, 2, 3] - null value kept
     *
     * @template T
     *
     * @param iterable<T>|string|null ...$iterables
     *
     * @return Generator<int, T>
     */
    public static function concat(mixed ...$iterables): Generator
    {
        foreach ($iterables as $iterable) {
            // Skip null arguments - cannot concatenate null
            if ($iterable === null) {
                continue;
            }

            foreach (self::toIterable($iterable) as $value) {
                yield $value;
            }
        }
    }

    /**
     * Interleaves multiple iterables.
     * Returns elements by taking one from each iterable in turn.
     * Continues until the first iterable is exhausted, padding others with null.
     *
     * Examples:
     *   interleave([1, 2, 3], ['a', 'b', 'c'])  // => [1, 'a', 2, 'b', 3, 'c']
     *   interleave([1, 2], ['a', 'b'], ['x', 'y'])  // => [1, 'a', 'x', 2, 'b', 'y']
     *   interleave([1, 2, 3], ['a', 'b'])  // => [1, 'a', 2, 'b', 3, null]
     *
     * @param mixed ...$iterables The sequences to interleave
     *
     * @return Generator<int, mixed>
     */
    public static function interleave(mixed ...$iterables): Generator
    {
        if ($iterables === []) {
            return;
        }

        $iterators = array_map(self::toIterator(...), $iterables);
        $first = $iterators[0] ?? null;

        if ($first === null) {
            return;
        }

        while ($first->valid()) {
            foreach ($iterators as $iterator) {
                if ($iterator->valid()) {
                    yield $iterator->current();
                    $iterator->next();
                } else {
                    yield null;
                }
            }
        }
    }

    /**
     * Returns elements from an iterable with a separator between them.
     * The separator is not added before the first element or after the last element.
     *
     * Examples:
     *   interpose(',', [1, 2, 3])  // => [1, ',', 2, ',', 3]
     *   interpose('-', 'abc')      // => ['a', '-', 'b', '-', 'c']
     *   interpose(',', [1])        // => [1]
     *
     * @template T
     * @template S
     *
     * @param S                  $separator The separator to insert between elements
     * @param iterable<T>|string $iterable  The input sequence
     *
     * @return Generator<int, S|T>
     */
    public static function interpose(mixed $separator, mixed $iterable): Generator
    {
        $first = true;
        foreach (self::toIterable($iterable) as $value) {
            if (!$first) {
                yield $separator;
            }

            yield $value;
            $first = false;
        }
    }

    /**
     * Maps a function over multiple iterables.
     * Applies the function to corresponding elements from each iterable.
     * Stops when the shortest iterable is exhausted.
     *
     * Examples:
     *   mapMulti(fn($a, $b) => $a + $b, [1, 2, 3], [10, 20, 30])  // => [11, 22, 33]
     *   mapMulti(fn($a, $b) => $a . $b, ['a', 'b'], ['1', '2'])   // => ['a1', 'b2']
     *   mapMulti(fn($a, $b) => $a + $b, [1, 2, 3], [10, 20])      // => [11, 22] (stops at shortest)
     *
     * @param callable $f            The mapping function
     * @param mixed    ...$iterables The sequences to map over
     *
     * @return Generator<int, mixed>
     */
    public static function mapMulti(callable $f, mixed ...$iterables): Generator
    {
        if ($iterables === []) {
            return;
        }

        $iterators = array_map(self::toIterator(...), $iterables);

        while (self::allIteratorsValid($iterators)) {
            $values = self::extractCurrentValues($iterators);
            yield $f(...$values);
        }
    }

    /**
     * Checks if all iterators in the array are still valid.
     *
     * @param Iterator<mixed, mixed>[] $iterators
     */
    private static function allIteratorsValid(array $iterators): bool
    {
        foreach ($iterators as $iterator) {
            if (!$iterator->valid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extracts current values from all iterators and advances them.
     *
     * @param Iterator<mixed, mixed>[] $iterators
     *
     * @return array<mixed>
     */
    private static function extractCurrentValues(array &$iterators): array
    {
        $values = [];
        foreach ($iterators as $iterator) {
            $values[] = $iterator->current();
            $iterator->next();
        }

        return $values;
    }

    /**
     * Converts an iterable or string to an Iterator.
     *
     * @return Iterator<int|string, mixed>
     */
    private static function toIterator(mixed $value): Iterator
    {
        $iterable = self::toIterable($value);

        if ($iterable instanceof Iterator) {
            return $iterable;
        }

        if (is_array($iterable)) {
            return new ArrayIterator($iterable);
        }

        return (static fn() => yield from $iterable)();
    }

    /**
     * @template T
     *
     * @param iterable<T>|string|null $value
     *
     * @return iterable<string|T>
     */
    private static function toIterable(mixed $value): iterable
    {
        if (is_string($value)) {
            return mb_str_split($value);
        }

        return $value ?? [];
    }
}
