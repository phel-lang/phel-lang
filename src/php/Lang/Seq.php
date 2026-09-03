<?php

declare(strict_types=1);

namespace Phel\Lang;

use Generator;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Generators\CombineGenerator;
use Phel\Lang\Generators\DedupeGenerator;
use Phel\Lang\Generators\FileGenerator;
use Phel\Lang\Generators\InfiniteGenerator;
use Phel\Lang\Generators\PartitionGenerator;
use Phel\Lang\Generators\SequenceGenerator;
use Phel\Lang\Generators\SliceGenerator;
use Phel\Lang\Generators\TransformGenerator;
use Traversable;
use TypeError;

use function array_values;
use function is_array;
use function is_string;
use function iterator_to_array;

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
        return SequenceGenerator::toIterable($value);
    }

    /**
     * Converts the final collection argument of `apply` to positional PHP args.
     *
     * @return list<mixed>
     */
    public static function toApplyArguments(mixed $value): array
    {
        if ($value instanceof PersistentMapInterface) {
            return self::mapToApplyArguments($value);
        }

        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            return iterator_to_array(SequenceGenerator::toIterator($value), false);
        }

        if (is_array($value)) {
            return array_values($value);
        }

        // A vector copies its trie into one array in a single walk, while
        // `iterator_to_array` resumes a generator once per element: 4.4x the
        // cost at three elements, 8.5x at sixteen. `apply` over a vector is
        // the common case, and it used to fall through to `Traversable`.
        //
        // Only vectors. The other sequential collections build their `toArray`
        // out of `iterator_to_array` themselves, so routing them here buys
        // nothing and costs a second pass (a list measured 0.89x).
        if ($value instanceof PersistentVectorInterface) {
            return array_values($value->toArray());
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value, false);
        }

        throw new TypeError('apply final argument must be nil, string, array, or Traversable');
    }

    /**
     * True when the iterable yields nothing. Pulls at most one element, so it
     * answers `empty?` for a source that has no size of its own (an
     * `eduction` pipeline, a generator) without draining it the way `count`
     * would have to.
     *
     * @param iterable<mixed> $coll
     */
    public static function isEmpty(iterable $coll): bool
    {
        foreach ($coll as $ignored) {
            return false;
        }

        return true;
    }

    /**
     * The first element the iterable yields, or `null` when it yields nothing.
     *
     * Pulls exactly one element, so it answers `first` for a source with no
     * indexed access of its own on the same terms as {@see self::isEmpty()}:
     * one element is all the question needs, whereas `count` would have to
     * drain the whole pipeline and still cache nothing, which is why `count`
     * refuses such a source instead.
     *
     * @param iterable<mixed> $coll
     */
    public static function first(iterable $coll): mixed
    {
        foreach ($coll as $value) {
            return $value;
        }

        return null;
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
        return CombineGenerator::concat(...$iterables);
    }

    /**
     * @param callable(mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function mapcat(callable $f, mixed $iterable): Generator
    {
        return TransformGenerator::mapcat($f, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function interpose(mixed $separator, mixed $iterable): Generator
    {
        return CombineGenerator::interpose($separator, $iterable);
    }

    /**
     * @param callable(int, mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    /**
     * @param callable(mixed, mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function reductions(callable $f, mixed $init, mixed $iterable): Generator
    {
        return TransformGenerator::reductions($f, $init, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function flatten(mixed $iterable): Generator
    {
        return TransformGenerator::flatten($iterable);
    }

    public static function mapIndexed(callable $f, mixed $iterable): Generator
    {
        return TransformGenerator::mapIndexed($f, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function interleave(mixed ...$iterables): Generator
    {
        return CombineGenerator::interleave(...$iterables);
    }

    /**
     * @param callable(mixed...):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function mapMulti(callable $f, mixed ...$iterables): Generator
    {
        return CombineGenerator::mapMulti($f, ...$iterables);
    }

    /**
     * @return Generator<int, int>
     */
    public static function infiniteRange(): Generator
    {
        return InfiniteGenerator::range();
    }

    /**
     * @return Generator<int, float|int>
     */
    public static function range(int|float $start, int|float $end, int|float $step): Generator
    {
        return SequenceGenerator::range($start, $end, $step);
    }

    /**
     * @param callable(mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function map(callable $f, mixed $iterable): Generator
    {
        return TransformGenerator::map($f, $iterable);
    }

    /**
     * A Phel predicate returns an arbitrary value, not a `bool`: only `nil`
     * and `false` are logically false, so the return type is `mixed`.
     *
     * @param callable(mixed):mixed $predicate
     *
     * @return Generator<int, mixed>
     */
    public static function filter(callable $predicate, mixed $iterable): Generator
    {
        return TransformGenerator::filter($predicate, $iterable);
    }

    /**
     * @param callable(mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function keep(callable $f, mixed $iterable): Generator
    {
        return TransformGenerator::keep($f, $iterable);
    }

    /**
     * @param callable(int, mixed):mixed $f
     *
     * @return Generator<int, mixed>
     */
    public static function keepIndexed(callable $f, mixed $iterable): Generator
    {
        return TransformGenerator::keepIndexed($f, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function take(int $n, mixed $iterable): Generator
    {
        return SliceGenerator::take($n, $iterable);
    }

    /**
     * @param callable(mixed):mixed $predicate
     *
     * @return Generator<int, mixed>
     */
    public static function takeWhile(callable $predicate, mixed $iterable): Generator
    {
        return SliceGenerator::takeWhile($predicate, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function takeNth(int $n, mixed $iterable): Generator
    {
        return SliceGenerator::takeNth($n, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function drop(int $n, mixed $iterable): Generator
    {
        return SliceGenerator::drop($n, $iterable);
    }

    /**
     * @param callable(mixed):mixed $predicate
     *
     * @return Generator<int, mixed>
     */
    public static function dropWhile(callable $predicate, mixed $iterable): Generator
    {
        return SliceGenerator::dropWhile($predicate, $iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function distinct(mixed $iterable): Generator
    {
        return DedupeGenerator::distinct($iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function dedupe(mixed $iterable): Generator
    {
        return DedupeGenerator::dedupe($iterable);
    }

    /**
     * @return Generator<int, mixed>
     */
    public static function compact(mixed $iterable, mixed ...$values): Generator
    {
        return DedupeGenerator::compact($iterable, ...$values);
    }

    /**
     * @return Generator<int, PersistentVectorInterface<mixed>>
     */
    public static function partition(int $n, mixed $iterable): Generator
    {
        return PartitionGenerator::partition($n, $iterable);
    }

    /**
     * @return Generator<int, PersistentVectorInterface<mixed>>
     */
    public static function partitionAll(int $n, mixed $iterable): Generator
    {
        return PartitionGenerator::partitionAll($n, $iterable);
    }

    /**
     * @param callable(mixed):mixed $f
     *
     * @return Generator<int, PersistentVectorInterface<mixed>>
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
     * @return Generator<int, PersistentVectorInterface<mixed>>
     */
    public static function csvLines(
        string $filename,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
    ): Generator {
        return FileGenerator::csvLines($filename, $separator, $enclosure, $escape);
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $value
     *
     * @return list<PersistentVectorInterface<mixed>>
     */
    private static function mapToApplyArguments(PersistentMapInterface $value): array
    {
        $typeFactory = TypeFactory::getInstance();
        $args = [];

        foreach ($value as $key => $item) {
            $args[] = $typeFactory->persistentVectorFromArray([$key, $item]);
        }

        return $args;
    }
}
