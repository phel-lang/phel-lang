<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use Generator;
use Phel;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Equalizer;

use function count;
use function is_string;
use function mb_str_split;

/**
 * Partitioning generators.
 * Provides generators for partitioning sequences into chunks.
 */
final class PartitionGenerators
{
    /**
     * Partitions an iterable into chunks of size n.
     * Only yields complete partitions (drops incomplete final partition).
     *
     * @template T
     *
     * @param int                $n        The partition size
     * @param iterable<T>|string $iterable The input sequence
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partition(int $n, mixed $iterable): Generator
    {
        if ($n <= 0) {
            return;
        }

        $partition = [];
        foreach (self::toIterable($iterable) as $value) {
            $partition[] = $value;

            if (count($partition) === $n) {
                yield Phel::vector($partition);
                $partition = [];
            }
        }
    }

    /**
     * Partitions an iterable into chunks of size n.
     * Yields all partitions including incomplete final partition.
     *
     * @template T
     *
     * @param int                $n        The partition size
     * @param iterable<T>|string $iterable The input sequence
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionAll(int $n, mixed $iterable): Generator
    {
        if ($n <= 0) {
            return;
        }

        $partition = [];
        foreach (self::toIterable($iterable) as $value) {
            $partition[] = $value;

            if (count($partition) === $n) {
                yield Phel::vector($partition);
                $partition = [];
            }
        }

        if ($partition !== []) {
            yield Phel::vector($partition);
        }
    }

    /**
     * @template T
     *
     * @param callable(T):mixed  $f
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionBy(callable $f, mixed $iterable): Generator
    {
        $equalizer = new Equalizer();
        $partition = [];
        $prevKey = null;
        $first = true;

        foreach (self::toIterable($iterable) as $value) {
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
    private static function toIterable(mixed $value): iterable
    {
        if (is_string($value)) {
            return mb_str_split($value);
        }

        return $value ?? [];
    }
}
