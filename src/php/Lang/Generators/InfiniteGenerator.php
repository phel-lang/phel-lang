<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use Generator;

final class InfiniteGenerator
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
        while (true) {
            yield $x;
            $x = $f($x);
        }
    }

    /**
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, string|T>
     */
    public static function cycle(mixed $iterable): Generator
    {
        $values = [];
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            $values[] = $value;
            yield $value;
        }

        if ($values === []) {
            return;
        }

        while (true) {
            foreach ($values as $value) {
                yield $value;
            }
        }
    }

    /**
     * @return Generator<int, int>
     */
    public static function range(): Generator
    {
        $i = 0;
        while (true) {
            yield $i++;
        }
    }

}
