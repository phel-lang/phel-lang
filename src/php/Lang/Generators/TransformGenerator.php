<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use Generator;

use function is_string;
use function mb_str_split;

/**
 * Element-wise transformation generators: map, filter, and their variants.
 *
 * Each operation walks a single input sequence and produces a lazy sequence of
 * transformed (or selectively kept) values. Order-preserving, one-pass, and free
 * of cross-element state beyond an optional running index.
 */
final class TransformGenerator
{
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
