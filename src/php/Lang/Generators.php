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
}
