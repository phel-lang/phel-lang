<?php

declare(strict_types=1);

namespace Phel\Lang;

use Generator;

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
        $seen = [];
        foreach ($iterable as $value) {
            // Use spl_object_hash for objects, regular value for primitives
            if (is_object($value)) {
                $key = spl_object_hash($value);
            } else {
                $key = serialize($value);
            }

            if (!isset($seen[$key])) {
                $seen[$key] = true;
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
        $first = true;
        $prev = null;
        foreach ($iterable as $value) {
            if ($first || $value !== $prev) {
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
     * @return Generator<int, list<T>>
     */
    public static function partitionBy(callable $f, iterable $iterable): Generator
    {
        $partition = [];
        $prevKey = null;
        $first = true;

        foreach ($iterable as $value) {
            $key = $f($value);

            if ($first || $key === $prevKey) {
                $partition[] = $value;
                $prevKey = $key;
                $first = false;
            } else {
                yield \Phel::vector($partition);
                $partition = [$value];
                $prevKey = $key;
            }
        }

        if ($partition !== []) {
            yield \Phel::vector($partition);
        }
    }
}
