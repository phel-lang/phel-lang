<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use Generator;
use Phel\Lang\Truthy;

/**
 * Positional slicing generators: take and drop and their variants.
 *
 * Each operation consumes a single input sequence and produces a lazy sub-sequence
 * based on element position or a predicate boundary. No transformation is performed;
 * elements pass through unchanged.
 */
final class SliceGenerator
{
    /**
     * Returns the first n elements from an iterable.
     *
     * Examples:
     *   take(3, [1, 2, 3, 4, 5])  // => [1, 2, 3]
     *   take(10, [1, 2, 3])       // => [1, 2, 3] (fewer than n available)
     *   take(0, [1, 2, 3])        // => []
     *
     * @return Generator<int, mixed>
     */
    public static function take(int $n, mixed $iterable): Generator
    {
        if ($n <= 0) {
            return;
        }

        $count = 0;
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            yield $value;
            ++$count;
            if ($count >= $n) {
                return;
            }
        }
    }

    /**
     * Returns elements while the predicate returns a logical true value; stops
     * at the first logically false one.
     *
     * Examples:
     *   takeWhile(fn($x) => $x < 4, [1, 2, 3, 4, 5])  // => [1, 2, 3]
     *   takeWhile(fn($x) => $x < 0, [1, 2, 3])        // => []
     *   takeWhile(fn($x) => $x > 0, [1, 2, 3])        // => [1, 2, 3]
     *
     * The predicate returns an arbitrary Phel value, not a `bool`, so the
     * boundary is decided by {@see Truthy::isTruthy()}: only `nil`
     * and `false` stop the sequence. PHP truthiness would also stop it on
     * `0`, `0.0`, `''`, `'0'` and `[]`.
     *
     * @param callable(mixed):mixed $predicate
     *
     * @return Generator<int, mixed>
     */
    public static function takeWhile(callable $predicate, mixed $iterable): Generator
    {
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            if (!Truthy::isTruthy($predicate($value))) {
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
     * @return Generator<int, mixed>
     */
    public static function takeNth(int $n, mixed $iterable): Generator
    {
        foreach (SequenceGenerator::indexed($iterable) as [$index, $value]) {
            if ($index % $n === 0) {
                yield $value;
            }
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
     * @return Generator<int, mixed>
     */
    public static function drop(int $n, mixed $iterable): Generator
    {
        $count = 0;
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            if ($count >= $n) {
                yield $value;
            }

            ++$count;
        }
    }

    /**
     * Skips elements while the predicate returns a logical true value; returns
     * the rest.
     *
     * Examples:
     *   dropWhile(fn($x) => $x < 3, [1, 2, 3, 4, 5])  // => [3, 4, 5]
     *   dropWhile(fn($x) => $x < 0, [1, 2, 3])        // => [1, 2, 3]
     *   dropWhile(fn($x) => $x > 0, [1, 2, 3])        // => []
     *
     * The predicate returns an arbitrary Phel value, not a `bool`, so the
     * boundary is decided by {@see Truthy::isTruthy()}: only `nil`
     * and `false` end the dropped prefix. PHP truthiness would also end it on
     * `0`, `0.0`, `''`, `'0'` and `[]`.
     *
     * @param callable(mixed):mixed $predicate
     *
     * @return Generator<int, mixed>
     */
    public static function dropWhile(callable $predicate, mixed $iterable): Generator
    {
        $dropping = true;
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            if ($dropping && Truthy::isTruthy($predicate($value))) {
                continue;
            }

            $dropping = false;
            yield $value;
        }
    }

}
