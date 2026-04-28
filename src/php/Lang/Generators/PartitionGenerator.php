<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use Generator;
use Phel;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\TypeFactory;

use function count;

final class PartitionGenerator
{
    /**
     * Only yields complete partitions (drops incomplete final partition).
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partition(int $n, mixed $iterable): Generator
    {
        if ($n <= 0) {
            return;
        }

        $partition = [];
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
            $partition[] = $value;

            if (count($partition) === $n) {
                yield Phel::vector($partition);
                $partition = [];
            }
        }
    }

    /**
     * Yields all partitions including incomplete final partition.
     *
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionAll(int $n, mixed $iterable): Generator
    {
        if ($n <= 0) {
            return;
        }

        $partition = [];
        foreach (SequenceGenerator::toIterable($iterable) as $value) {
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
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionBy(callable $f, mixed $iterable): Generator
    {
        $equalizer = TypeFactory::getInstance()->getEqualizer();
        $partition = [];
        $prevKey = null;
        $first = true;

        foreach (SequenceGenerator::toIterable($iterable) as $value) {
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
