<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use Generator;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Reduced;
use Phel\Lang\Truthy;
use Phel\Lang\TypeFactory;

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
     * For `PersistentMap` inputs the iteration yields `[k v]` pair vectors,
     * matching Clojure's seq-on-a-map semantics: `(map identity {:k :v})`
     * produces `[[:k :v]]`, not `[:v]`.
     *
     * @param callable(mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function map(callable $f, mixed $iterable): Generator
    {
        if ($iterable instanceof PersistentMapInterface) {
            $typeFactory = TypeFactory::getInstance();
            foreach ($iterable as $k => $v) {
                yield $f($typeFactory->persistentVectorFromArray([$k, $v]));
            }

            return;
        }

        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            yield $f($value);
        }
    }

    /**
     * Returns elements for which the predicate returns a logical true value.
     *
     * Examples:
     *   filter(fn($x) => $x > 2, [1, 2, 3, 4, 5])  // => [3, 4, 5]
     *   filter(fn($c) => $c !== 'b', 'abc')        // => ['a', 'c']
     *
     * The predicate returns an arbitrary Phel value, not a `bool`: `(filter
     * identity ...)` and any predicate ending in a lookup or an `or` hands
     * back whatever it found. Only `nil` and `false` are logically false in
     * Phel, so the keep/drop decision goes through {@see Truthy::isTruthy()}
     * and never through PHP truthiness, which would also drop `0`, `0.0`,
     * `''`, `'0'` and `[]`.
     *
     * @param callable(mixed):mixed $predicate
     *
     * @return Generator<int, mixed>
     */
    public static function filter(callable $predicate, mixed $iterable): Generator
    {
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            if (Truthy::isTruthy($predicate($value))) {
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
     * @param callable(mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function keep(callable $f, mixed $iterable): Generator
    {
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
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
     * @param callable(int, mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function keepIndexed(callable $f, mixed $iterable): Generator
    {
        foreach (SequenceGenerator::indexed($iterable) as [$index, $value]) {
            $result = $f($index, $value);
            if ($result !== null) {
                yield $result;
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
     * @return Generator<int, mixed>
     */
    public static function mapcat(callable $f, mixed $iterable): Generator
    {
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            $result = $f($value);

            // Skip null results - they contribute nothing to concatenation
            if ($result === null) {
                continue;
            }

            foreach (SequenceGenerator::toIterable($result) as $item) {
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
     * @param callable(int, mixed):mixed $f The mapping function that takes index and value
     *
     * @return Generator<int, mixed>
     */
    /**
     * The intermediate accumulator values of a reduction, `init` first.
     *
     * Written here as a generator rather than in Phel, where each element cost
     * a `lazy-seq` thunk plus `seq`, `first`, `rest`, `cons` and a recursive
     * call. The sequence stays lazy: a generator yields nothing until pulled,
     * so `(reductions + (iterate inc 1))` over an infinite source is still
     * safe and still unrealized.
     *
     * A `Reduced` returned by `$f` ends the reduction, and its unwrapped value
     * is the last element yielded, matching what the recursive spelling did by
     * re-entering its own `reduced?` guard.
     *
     * @param callable(mixed, mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function reductions(callable $f, mixed $init, mixed $iterable): Generator
    {
        if ($init instanceof Reduced) {
            yield $init->deref();

            return;
        }

        yield $init;

        $acc = $init;
        foreach (self::entriesOf($iterable) as $value) {
            $acc = $f($acc, $value);

            if ($acc instanceof Reduced) {
                yield $acc->deref();

                return;
            }

            yield $acc;
        }
    }

    public static function mapIndexed(callable $f, mixed $iterable): Generator
    {
        foreach (SequenceGenerator::indexed($iterable) as [$index, $value]) {
            yield $f($index, $value);
        }
    }

    /**
     * A map is walked as `[key value]` pairs, as {@see self::map()} walks it,
     * not as bare values: `(reductions + 0 {:a 1})` has to reach `+` with the
     * entry it always did, which is what makes it raise rather than quietly
     * summing the values.
     *
     * @return Generator<int, mixed>
     */
    private static function entriesOf(mixed $iterable): Generator
    {
        if ($iterable instanceof PersistentMapInterface) {
            $typeFactory = TypeFactory::getInstance();
            foreach ($iterable as $k => $v) {
                yield $typeFactory->persistentVectorFromArray([$k, $v]);
            }

            return;
        }

        // Values, not `yield from`: that would carry the source's keys
        // through, and this generator is declared with `int` keys.
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            yield $value;
        }
    }
}
