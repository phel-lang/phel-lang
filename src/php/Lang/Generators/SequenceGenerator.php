<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use ArrayIterator;
use Generator;
use Iterator;
use Phel\Lang\TypeFactory;

use function count;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function mb_str_split;

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
     * Maps a function over an iterable and concatenates (flattens) the results.
     *
     * This is equivalent to: (apply concat (apply map f coll))
     *
     * IMPORTANT: If the mapping function returns null or an empty iterable for an
     * element, that element contributes nothing to the output. This is a FEATURE
     * (matching Clojure semantics) that allows selective filtering during mapping:
     *
     *   // Example: flatten only even numbers
     *   mapcat(fn($x) => $x % 2 === 0 ? [$x, $x] : null, [1, 2, 3, 4])
     *   // => [2, 2, 4, 4]
     *
     * If you need to preserve null values or want explicit filtering, use:
     *   - filter() + mapcat() for predicate-based filtering
     *   - keep() for mapping with automatic null-removal
     *   - compact() for removing nulls from existing collections
     *
     * @template T
     * @template U
     *
     * @param callable(T): (iterable<U>|string|null) $f        The mapping function
     * @param iterable<T>|string                     $iterable The input sequence
     *
     * @return Generator<int, string|U>
     */
    public static function mapcat(callable $f, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
            $result = $f($value);

            // Skip null results - they contribute nothing to concatenation
            if ($result === null) {
                continue;
            }

            foreach (self::toIterable($result) as $item) {
                yield $item;
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
     * Maps a function over an iterable with index.
     * Applies the function to each element along with its index (0-based).
     *
     * Examples:
     *   mapIndexed(fn($i, $v) => "$i:$v", ['a', 'b', 'c'])  // => ['0:a', '1:b', '2:c']
     *   mapIndexed(fn($i, $v) => $i * $v, [1, 2, 3])        // => [0, 2, 6]
     *
     * @template T
     * @template U
     *
     * @param callable(int, T): U $f        The mapping function that takes index and value
     * @param iterable<T>|string  $iterable The input sequence
     *
     * @return Generator<int, U>
     */
    public static function mapIndexed(callable $f, mixed $iterable): Generator
    {
        $index = 0;
        foreach (self::toIterable($iterable) as $value) {
            yield $f($index, $value);
            ++$index;
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
            ? static fn (int|float $i, int|float $e): bool => $i > $e
            : static fn (int|float $i, int|float $e): bool => $i < $e;

        for ($i = $start; $cmp($i, $end); $i += $step) {
            yield $i;
        }
    }

    /**
     * Applies a function to each element of an iterable, returning the results.
     *
     * Examples:
     *   map(fn($x) => $x * 2, [1, 2, 3])      // => [2, 4, 6]
     *   map(fn($c) => strtoupper($c), 'abc')  // => ['A', 'B', 'C']
     *
     * @template T
     * @template U
     *
     * @param callable(T):U      $f
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, U>
     */
    public static function map(callable $f, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
            yield $f($value);
        }
    }

    /**
     * Returns elements for which the predicate returns true.
     *
     * Examples:
     *   filter(fn($x) => $x > 2, [1, 2, 3, 4, 5])  // => [3, 4, 5]
     *   filter(fn($c) => $c !== 'b', 'abc')        // => ['a', 'c']
     *
     * @template T
     *
     * @param callable(T):bool   $predicate
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function filter(callable $predicate, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
            if ($predicate($value)) {
                yield $value;
            }
        }
    }

    /**
     * Applies a function to each element and returns non-null results.
     * Unlike filter(), keep() both transforms and filters in one operation.
     *
     * Examples:
     *   keep(fn($x) => $x > 2 ? $x * 10 : null, [1, 2, 3, 4])  // => [30, 40]
     *   keep(fn($x) => $x % 2 === 0 ? $x : null, [1, 2, 3, 4]) // => [2, 4]
     *
     * @template T
     * @template U
     *
     * @param callable(T):?U     $f
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, U>
     */
    public static function keep(callable $f, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
            $result = $f($value);
            if ($result !== null) {
                yield $result;
            }
        }
    }

    /**
     * Like keep(), but the function also receives the element's index.
     *
     * Examples:
     *   keepIndexed(fn($i, $v) => $i % 2 === 0 ? $v : null, ['a', 'b', 'c', 'd'])  // => ['a', 'c']
     *   keepIndexed(fn($i, $v) => $i > 0 ? "$i:$v" : null, ['a', 'b', 'c'])        // => ['1:b', '2:c']
     *
     * @template T
     * @template U
     *
     * @param callable(int, T):?U $f
     * @param iterable<T>|string  $iterable
     *
     * @return Generator<int, U>
     */
    public static function keepIndexed(callable $f, mixed $iterable): Generator
    {
        $index = 0;
        foreach (self::toIterable($iterable) as $value) {
            $result = $f($index, $value);
            if ($result !== null) {
                yield $result;
            }

            ++$index;
        }
    }

    /**
     * Returns the first n elements from an iterable.
     *
     * Examples:
     *   take(3, [1, 2, 3, 4, 5])  // => [1, 2, 3]
     *   take(10, [1, 2, 3])       // => [1, 2, 3] (fewer than n available)
     *   take(0, [1, 2, 3])        // => []
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function take(int $n, mixed $iterable): Generator
    {
        $count = 0;
        foreach (self::toIterable($iterable) as $value) {
            if ($count >= $n) {
                break;
            }

            yield $value;
            ++$count;
        }
    }

    /**
     * Returns elements while the predicate returns true; stops at first false.
     *
     * Examples:
     *   takeWhile(fn($x) => $x < 4, [1, 2, 3, 4, 5])  // => [1, 2, 3]
     *   takeWhile(fn($x) => $x < 0, [1, 2, 3])        // => []
     *   takeWhile(fn($x) => $x > 0, [1, 2, 3])        // => [1, 2, 3]
     *
     * @template T
     *
     * @param callable(T):bool   $predicate
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function takeWhile(callable $predicate, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
            if (!$predicate($value)) {
                break;
            }

            yield $value;
        }
    }

    /**
     * Returns every nth element from an iterable (starting at index 0).
     *
     * Examples:
     *   takeNth(2, [1, 2, 3, 4, 5, 6])  // => [1, 3, 5]
     *   takeNth(3, [1, 2, 3, 4, 5, 6])  // => [1, 4]
     *   takeNth(1, [1, 2, 3])           // => [1, 2, 3]
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function takeNth(int $n, mixed $iterable): Generator
    {
        $index = 0;
        foreach (self::toIterable($iterable) as $value) {
            if ($index % $n === 0) {
                yield $value;
            }

            ++$index;
        }
    }

    /**
     * Skips the first n elements and returns the rest.
     *
     * Examples:
     *   drop(2, [1, 2, 3, 4, 5])  // => [3, 4, 5]
     *   drop(10, [1, 2, 3])       // => []
     *   drop(0, [1, 2, 3])        // => [1, 2, 3]
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function drop(int $n, mixed $iterable): Generator
    {
        $count = 0;
        foreach (self::toIterable($iterable) as $value) {
            if ($count >= $n) {
                yield $value;
            }

            ++$count;
        }
    }

    /**
     * Skips elements while the predicate returns true; returns the rest.
     *
     * Examples:
     *   dropWhile(fn($x) => $x < 3, [1, 2, 3, 4, 5])  // => [3, 4, 5]
     *   dropWhile(fn($x) => $x < 0, [1, 2, 3])        // => [1, 2, 3]
     *   dropWhile(fn($x) => $x > 0, [1, 2, 3])        // => []
     *
     * @template T
     *
     * @param callable(T):bool   $predicate
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function dropWhile(callable $predicate, mixed $iterable): Generator
    {
        $dropping = true;
        foreach (self::toIterable($iterable) as $value) {
            if ($dropping && $predicate($value)) {
                continue;
            }

            $dropping = false;
            yield $value;
        }
    }

    /**
     * Returns unique elements from an iterable, preserving first occurrence order.
     * Uses hash-based equality checking for efficient deduplication.
     *
     * Examples:
     *   distinct([1, 2, 1, 3, 2, 4])  // => [1, 2, 3, 4]
     *   distinct('abracadabra')       // => ['a', 'b', 'r', 'c', 'd']
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function distinct(mixed $iterable): Generator
    {
        $typeFactory = TypeFactory::getInstance();
        $hasher = $typeFactory->getHasher();
        $equalizer = $typeFactory->getEqualizer();
        $seen = [];

        foreach (self::toIterable($iterable) as $value) {
            $hash = $hasher->hash($value);

            // Check if we've seen an equal value with this hash
            $found = false;
            if (isset($seen[$hash])) {
                foreach ($seen[$hash] as $seenValue) {
                    if ($equalizer->equals($value, $seenValue)) {
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $seen[$hash][] = $value;
                yield $value;
            }
        }
    }

    /**
     * Removes consecutive duplicate elements from an iterable.
     * Unlike distinct(), only removes duplicates that are adjacent to each other.
     *
     * Examples:
     *   dedupe([1, 1, 2, 2, 2, 3, 1, 1])  // => [1, 2, 3, 1]
     *   dedupe('aabbcc')                  // => ['a', 'b', 'c']
     *   dedupe([1, 2, 3, 4])              // => [1, 2, 3, 4] (no consecutive dupes)
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function dedupe(mixed $iterable): Generator
    {
        $equalizer = TypeFactory::getInstance()->getEqualizer();
        $first = true;
        $prev = null;

        foreach (self::toIterable($iterable) as $value) {
            if ($first || !$equalizer->equals($value, $prev)) {
                yield $value;
                $prev = $value;
                $first = false;
            }
        }
    }

    /**
     * Removes null values (and optionally other specified values) from an iterable.
     *
     * This provides an explicit, composable way to filter out unwanted sentinel values
     * from a collection. Inspired by loophp/collection's Compact operation.
     *
     * Uses strict comparison (===) to preserve type safety. For example:
     *   - null and '' (empty string) are distinct
     *   - 0 (int) and '0' (string) are distinct
     *   - false and 0 are distinct
     *
     * Examples:
     *   compact([1, null, 2, null, 3])
     *   // => [1, 2, 3]
     *
     *   compact([1, null, 2, false, 3], null, false)
     *   // => [1, 2, 3]
     *
     *   compact([null, '', 0, false], null)
     *   // => ['', 0, false]  - only null removed, not similar values
     *
     * Unlike filter(), compact() is specifically designed for removing unwanted
     * sentinel values, making intent clearer in code.
     *
     * @template T
     *
     * @param iterable<T>|string $iterable  The input sequence
     * @param mixed              ...$values Values to remove (default: just null)
     *
     * @return Generator<int, T>
     */
    public static function compact(mixed $iterable, mixed ...$values): Generator
    {
        $valuesToRemove = $values === [] ? [null] : $values;

        if (count($valuesToRemove) === 1) {
            yield from self::compactSingleValue($iterable, $valuesToRemove[0]);
            return;
        }

        $lookups = self::prepareCompactLookups($valuesToRemove);

        foreach (self::toIterable($iterable) as $item) {
            if (!self::shouldRemoveItem($item, $lookups['scalarLookup'], $lookups['objects'])) {
                yield $item;
            }
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
     * Optimized path for removing a single value from an iterable.
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    private static function compactSingleValue(mixed $iterable, mixed $valueToRemove): Generator
    {
        foreach (self::toIterable($iterable) as $item) {
            if ($item !== $valueToRemove) {
                yield $item;
            }
        }
    }

    /**
     * Prepares lookup structures for efficient value removal.
     * Separates scalars (hashable) from objects (identity comparison).
     *
     * @param array<mixed> $valuesToRemove
     *
     * @return array{scalarLookup: array<string, true>, objects: array<object>}
     */
    private static function prepareCompactLookups(array $valuesToRemove): array
    {
        $scalarLookup = [];
        $objects = [];

        foreach ($valuesToRemove as $value) {
            if (is_object($value)) {
                $objects[] = $value;
            } else {
                $scalarLookup[var_export($value, true)] = true;
            }
        }

        return ['scalarLookup' => $scalarLookup, 'objects' => $objects];
    }

    /**
     * Determines if an item should be removed based on lookup structures.
     *
     * @param array<string, true> $scalarLookup
     * @param array<object>       $objectsToRemove
     */
    private static function shouldRemoveItem(mixed $item, array $scalarLookup, array $objectsToRemove): bool
    {
        if (is_object($item)) {
            return in_array($item, $objectsToRemove, true);
        }

        return isset($scalarLookup[var_export($item, true)]);
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

        return (static fn () => yield from $iterable)();
    }
}
