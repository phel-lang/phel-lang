<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use Generator;

use function is_string;
use function mb_str_split;

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
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, string|T>
     */
    public static function cycle(mixed $iterable): Generator
    {
        $values = [];
        foreach (self::toIterable($iterable) as $value) {
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
