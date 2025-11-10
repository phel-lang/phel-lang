<?php

declare(strict_types=1);

namespace Phel\Lang;

use Generator;
use Phel;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;

final class Generators
{
    /**
     * @template T
     *
     * @param T $value
     *
     * @return Generator<int, T>
     */
    public static function repeat(mixed $value): Generator
    {
        // @phpstan-ignore-next-line while.alwaysTrue
        while (true) {
            yield $value;
        }
    }

    /**
     * @template T
     *
     * @param callable():T $f
     *
     * @return Generator<int, T>
     */
    public static function repeatedly(callable $f): Generator
    {
        // @phpstan-ignore-next-line while.alwaysTrue
        while (true) {
            yield $f();
        }
    }

    /**
     * @template T
     *
     * @param callable(T):T $f
     * @param T             $x
     *
     * @return Generator<int, T>
     */
    public static function iterate(callable $f, mixed $x): Generator
    {
        // @phpstan-ignore-next-line while.alwaysTrue
        while (true) {
            yield $x;
            $x = $f($x);
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     *
     * @return Generator<int, T>
     */
    public static function cycle(iterable $iterable): Generator
    {
        $values = [];
        foreach ($iterable as $value) {
            $values[] = $value;
            yield $value;
        }

        if ($values === []) {
            return;
        }

        // @phpstan-ignore-next-line while.alwaysTrue
        while (true) {
            foreach ($values as $value) {
                yield $value;
            }
        }
    }

    /**
     * Concatenates multiple iterables into a single lazy sequence.
     * Yields all elements from the first iterable, then all from the second, etc.
     * Handles null values by skipping them.
     *
     * @template T
     *
     * @param iterable<T>|null ...$iterables
     *
     * @return Generator<int, T>
     */
    public static function concat(iterable|null ...$iterables): Generator
    {
        foreach ($iterables as $iterable) {
            if ($iterable === null) {
                continue;
            }

            foreach ($iterable as $value) {
                yield $value;
            }
        }
    }

    /**
     * Maps a function over an iterable and concatenates the results.
     * The function should return an iterable for each input element.
     * Yields all elements from each resulting iterable in sequence.
     *
     * @template T
     * @template U
     *
     * @param callable(T): (iterable<U>|null) $f        The mapping function
     * @param iterable<T>                     $iterable The input sequence
     *
     * @return Generator<int, U>
     */
    public static function mapcat(callable $f, iterable $iterable): Generator
    {
        foreach ($iterable as $value) {
            $result = $f($value);
            if ($result === null) {
                continue;
            }

            foreach ($result as $item) {
                yield $item;
            }
        }
    }

    /**
     * Returns elements from an iterable with a separator between them.
     * The separator is not added before the first element or after the last element.
     *
     * @template T
     * @template S
     *
     * @param S           $separator The separator to insert between elements
     * @param iterable<T> $iterable  The input sequence
     *
     * @return Generator<int, S|T>
     */
    public static function interpose(mixed $separator, iterable $iterable): Generator
    {
        $first = true;
        foreach ($iterable as $value) {
            if (!$first) {
                yield $separator;
            }

            yield $value;
            $first = false;
        }
    }

    /**
     * Generates a range of numbers [start, end) with given step.
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
     * @template T
     * @template U
     *
     * @param callable(T):U $f
     * @param iterable<T>   $iterable
     *
     * @return Generator<int, U>
     */
    public static function map(callable $f, iterable $iterable): Generator
    {
        foreach ($iterable as $value) {
            yield $f($value);
        }
    }

    /**
     * @template T
     *
     * @param callable(T):bool $predicate
     * @param iterable<T>      $iterable
     *
     * @return Generator<int, T>
     */
    public static function filter(callable $predicate, iterable $iterable): Generator
    {
        foreach ($iterable as $value) {
            if ($predicate($value)) {
                yield $value;
            }
        }
    }

    /**
     * @template T
     * @template U
     *
     * @param callable(T):?U $f
     * @param iterable<T>    $iterable
     *
     * @return Generator<int, U>
     */
    public static function keep(callable $f, iterable $iterable): Generator
    {
        foreach ($iterable as $value) {
            $result = $f($value);
            if ($result !== null) {
                yield $result;
            }
        }
    }

    /**
     * @template T
     * @template U
     *
     * @param callable(int, T):?U $f
     * @param iterable<T>         $iterable
     *
     * @return Generator<int, U>
     */
    public static function keepIndexed(callable $f, iterable $iterable): Generator
    {
        $index = 0;
        foreach ($iterable as $value) {
            $result = $f($index, $value);
            if ($result !== null) {
                yield $result;
            }

            ++$index;
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     *
     * @return Generator<int, T>
     */
    public static function take(int $n, iterable $iterable): Generator
    {
        $count = 0;
        foreach ($iterable as $value) {
            if ($count >= $n) {
                break;
            }

            yield $value;
            ++$count;
        }
    }

    /**
     * @template T
     *
     * @param callable(T):bool $predicate
     * @param iterable<T>      $iterable
     *
     * @return Generator<int, T>
     */
    public static function takeWhile(callable $predicate, iterable $iterable): Generator
    {
        foreach ($iterable as $value) {
            if (!$predicate($value)) {
                break;
            }

            yield $value;
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     *
     * @return Generator<int, T>
     */
    public static function takeNth(int $n, iterable $iterable): Generator
    {
        $index = 0;
        foreach ($iterable as $value) {
            if ($index % $n === 0) {
                yield $value;
            }

            ++$index;
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     *
     * @return Generator<int, T>
     */
    public static function drop(int $n, iterable $iterable): Generator
    {
        $count = 0;
        foreach ($iterable as $value) {
            if ($count >= $n) {
                yield $value;
            }

            ++$count;
        }
    }

    /**
     * @template T
     *
     * @param callable(T):bool $predicate
     * @param iterable<T>      $iterable
     *
     * @return Generator<int, T>
     */
    public static function dropWhile(callable $predicate, iterable $iterable): Generator
    {
        $dropping = true;
        foreach ($iterable as $value) {
            if ($dropping && $predicate($value)) {
                continue;
            }

            $dropping = false;
            yield $value;
        }
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     *
     * @return Generator<int, T>
     */
    public static function distinct(iterable $iterable): Generator
    {
        $hasher = new Hasher();
        $equalizer = new Equalizer();
        $seen = [];

        foreach ($iterable as $value) {
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
     * @template T
     *
     * @param iterable<T> $iterable
     *
     * @return Generator<int, T>
     */
    public static function dedupe(iterable $iterable): Generator
    {
        $equalizer = new Equalizer();
        $first = true;
        $prev = null;

        foreach ($iterable as $value) {
            if ($first || !$equalizer->equals($value, $prev)) {
                yield $value;
                $prev = $value;
                $first = false;
            }
        }
    }

    /**
     * @template T
     *
     * @param callable(T):mixed $f
     * @param iterable<T>       $iterable
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionBy(callable $f, iterable $iterable): Generator
    {
        $equalizer = new Equalizer();
        $partition = [];
        $prevKey = null;
        $first = true;

        foreach ($iterable as $value) {
            $key = $f($value);

            if ($first || $equalizer->equals($key, $prevKey)) {
                $partition[] = $value;
                $prevKey = $key;
                $first = false;
            } else {
                yield Phel::vector($partition);
                $partition = [$value];
                $prevKey = $key;
            }
        }

        if ($partition !== []) {
            yield Phel::vector($partition);
        }
    }
}
