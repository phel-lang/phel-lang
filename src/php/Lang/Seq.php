<?php

declare(strict_types=1);

namespace Phel\Lang;

use Generator;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Generators\FileGenerator;
use Phel\Lang\Generators\InfiniteGenerator;
use Phel\Lang\Generators\PartitionGenerator;
use Phel\Lang\Generators\SequenceGenerator;

use function is_string;
use function mb_str_split;

final class Seq
{
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
    public static function toIterable(mixed $value): iterable
    {
        if (is_string($value)) {
            return mb_str_split($value);
        }

        return $value ?? [];
    }

    /**
     * @template T
     *
     * @param T $value
     *
     * @return Generator<int, T>
     */
    public static function repeat(mixed $value): Generator
    {
        return InfiniteGenerator::repeat($value);
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
        return InfiniteGenerator::repeatedly($f);
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
        return InfiniteGenerator::iterate($f, $x);
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
        return InfiniteGenerator::cycle($iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function concat(mixed ...$iterables): Generator
    {
        return SequenceGenerator::concat(...$iterables);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function mapcat(callable $f, mixed $iterable): Generator
    {
        return SequenceGenerator::mapcat($f, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function interpose(mixed $separator, mixed $iterable): Generator
    {
        return SequenceGenerator::interpose($separator, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function mapIndexed(callable $f, mixed $iterable): Generator
    {
        return SequenceGenerator::mapIndexed($f, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function interleave(mixed ...$iterables): Generator
    {
        return SequenceGenerator::interleave(...$iterables);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function mapMulti(callable $f, mixed ...$iterables): Generator
    {
        return SequenceGenerator::mapMulti($f, ...$iterables);
    }

    /**
     * @return Generator<int, float|int>
     */
    public static function range(int|float $start, int|float $end, int|float $step): Generator
    {
        return SequenceGenerator::range($start, $end, $step);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function map(callable $f, mixed $iterable): Generator
    {
        return SequenceGenerator::map($f, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function filter(callable $predicate, mixed $iterable): Generator
    {
        return SequenceGenerator::filter($predicate, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function keep(callable $f, mixed $iterable): Generator
    {
        return SequenceGenerator::keep($f, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function keepIndexed(callable $f, mixed $iterable): Generator
    {
        return SequenceGenerator::keepIndexed($f, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function take(int $n, mixed $iterable): Generator
    {
        return SequenceGenerator::take($n, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function takeWhile(callable $predicate, mixed $iterable): Generator
    {
        return SequenceGenerator::takeWhile($predicate, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function takeNth(int $n, mixed $iterable): Generator
    {
        return SequenceGenerator::takeNth($n, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function drop(int $n, mixed $iterable): Generator
    {
        return SequenceGenerator::drop($n, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function dropWhile(callable $predicate, mixed $iterable): Generator
    {
        return SequenceGenerator::dropWhile($predicate, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function distinct(mixed $iterable): Generator
    {
        return SequenceGenerator::distinct($iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function dedupe(mixed $iterable): Generator
    {
        return SequenceGenerator::dedupe($iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function compact(mixed $iterable, mixed ...$values): Generator
    {
        return SequenceGenerator::compact($iterable, ...$values);
    }

    /**
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partition(int $n, mixed $iterable): Generator
    {
        return PartitionGenerator::partition($n, $iterable);
    }

    /**
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionAll(int $n, mixed $iterable): Generator
    {
        return PartitionGenerator::partitionAll($n, $iterable);
    }

    /**
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionBy(callable $f, mixed $iterable): Generator
    {
        return PartitionGenerator::partitionBy($f, $iterable);
    }

    /**
     * @return Generator<int, string>
     */
    public static function fileLines(string $filename): Generator
    {
        return FileGenerator::fileLines($filename);
    }

    /**
     * @return Generator<int, string>
     */
    public static function fileSeq(string $path): Generator
    {
        return FileGenerator::fileSeq($path);
    }

    /**
     * @return Generator<int, string>
     */
    public static function readFileChunks(string $filename, int $chunkSize = 8192): Generator
    {
        return FileGenerator::readFileChunks($filename, $chunkSize);
    }

    /**
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function csvLines(
        string $filename,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
    ): Generator {
        return FileGenerator::csvLines($filename, $separator, $enclosure, $escape);
    }
}
