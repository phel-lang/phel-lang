<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayIterator;
use Generator;
use Iterator;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Generators\FileGenerator;
use Phel\Lang\Generators\InfiniteGenerator;
use Phel\Lang\Generators\PartitionGenerator;

use function is_array;
use function is_string;
use function mb_str_split;

final class SequenceGenerator
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
     * @deprecated Use InfiniteGenerator::repeat() instead
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
     * @deprecated Use InfiniteGenerator::repeatedly() instead
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
     * @deprecated Use InfiniteGenerator::iterate() instead
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
     * @deprecated Use InfiniteGenerator::cycle() instead
     *
     * @return Generator<int, T>
     */
    public static function cycle(mixed $iterable): Generator
    {
        return InfiniteGenerator::cycle($iterable);
    }

    /**
     * Concatenates multiple iterables into a single lazy sequence.
     * Yields all elements from the first iterable, then all from the second, etc.
     * Handles null values by skipping them.
     *
     * @template T
     *
     * @param iterable<T>|string|null ...$iterables
     *
     * @return Generator<int, T>
     */
    public static function concat(mixed ...$iterables): Generator
    {
        foreach ($iterables as $iterable) {
            if ($iterable === null) {
                continue;
            }

            foreach (self::toIterable($iterable) as $value) {
                yield $value;
            }
        }
    }

    /**
     * Maps a function over an iterable and concatenates the results.
     * The function can return an iterable or string for each input element.
     * Yields all elements from each resulting iterable in sequence.
     *
     * @template T
     * @template U
     *
     * @param callable(T): (iterable<U>|string|null) $f        The mapping function
     * @param iterable<T>|string                     $iterable The input sequence
     *
     * @return Generator<int, string|U>
     */
    public static function mapcat(callable $f, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
            $result = $f($value);
            if ($result === null) {
                continue;
            }

            foreach (self::toIterable($result) as $item) {
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
     * @param S                  $separator The separator to insert between elements
     * @param iterable<T>|string $iterable  The input sequence
     *
     * @return Generator<int, S|T>
     */
    public static function interpose(mixed $separator, mixed $iterable): Generator
    {
        $first = true;
        foreach (self::toIterable($iterable) as $value) {
            if (!$first) {
                yield $separator;
            }

            yield $value;
            $first = false;
        }
    }

    /**
     * Maps a function over an iterable with index.
     * Applies the function to each element along with its index (0-based).
     *
     * @template T
     * @template U
     *
     * @param callable(int, T): U $f        The mapping function that takes index and value
     * @param iterable<T>|string  $iterable The input sequence
     *
     * @return Generator<int, U>
     */
    public static function mapIndexed(callable $f, mixed $iterable): Generator
    {
        $index = 0;
        foreach (self::toIterable($iterable) as $value) {
            yield $f($index, $value);
            ++$index;
        }
    }

    /**
     * Interleaves multiple iterables.
     * Returns elements by taking one from each iterable in turn.
     * Continues until the first iterable is exhausted, padding others with null.
     *
     * @param mixed ...$iterables The sequences to interleave
     *
     * @return Generator<int, mixed>
     */
    public static function interleave(mixed ...$iterables): Generator
    {
        if ($iterables === []) {
            return;
        }

        $iterators = array_map(self::toIterator(...), $iterables);
        $first = $iterators[0] ?? null;

        if ($first === null) {
            return;
        }

        while ($first->valid()) {
            foreach ($iterators as $iterator) {
                if ($iterator->valid()) {
                    yield $iterator->current();
                    $iterator->next();
                } else {
                    yield null;
                }
            }
        }
    }

    /**
     * Maps a function over multiple iterables.
     * Applies the function to corresponding elements from each iterable.
     * Stops when the shortest iterable is exhausted.
     *
     * @param callable $f            The mapping function
     * @param mixed    ...$iterables The sequences to map over
     *
     * @return Generator<int, mixed>
     */
    public static function mapMulti(callable $f, mixed ...$iterables): Generator
    {
        if ($iterables === []) {
            return;
        }

        $iterators = array_map(self::toIterator(...), $iterables);

        while (true) {
            $allValid = true;
            foreach ($iterators as $iterator) {
                if (!$iterator->valid()) {
                    $allValid = false;
                    break;
                }
            }

            if (!$allValid) {
                break;
            }

            $values = [];
            foreach ($iterators as $iterator) {
                $values[] = $iterator->current();
                $iterator->next();
            }

            yield $f(...$values);
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
     * @param callable(T):U      $f
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, U>
     */
    public static function map(callable $f, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
            yield $f($value);
        }
    }

    /**
     * @template T
     *
     * @param callable(T):bool   $predicate
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function filter(callable $predicate, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
            if ($predicate($value)) {
                yield $value;
            }
        }
    }

    /**
     * @template T
     * @template U
     *
     * @param callable(T):?U     $f
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, U>
     */
    public static function keep(callable $f, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
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
     * @param iterable<T>|string  $iterable
     *
     * @return Generator<int, U>
     */
    public static function keepIndexed(callable $f, mixed $iterable): Generator
    {
        $index = 0;
        foreach (self::toIterable($iterable) as $value) {
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
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function take(int $n, mixed $iterable): Generator
    {
        $count = 0;
        foreach (self::toIterable($iterable) as $value) {
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
     * @param callable(T):bool   $predicate
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function takeWhile(callable $predicate, mixed $iterable): Generator
    {
        foreach (self::toIterable($iterable) as $value) {
            if (!$predicate($value)) {
                break;
            }

            yield $value;
        }
    }

    /**
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function takeNth(int $n, mixed $iterable): Generator
    {
        $index = 0;
        foreach (self::toIterable($iterable) as $value) {
            if ($index % $n === 0) {
                yield $value;
            }

            ++$index;
        }
    }

    /**
     * @template T
     *
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function drop(int $n, mixed $iterable): Generator
    {
        $count = 0;
        foreach (self::toIterable($iterable) as $value) {
            if ($count >= $n) {
                yield $value;
            }

            ++$count;
        }
    }

    /**
     * @template T
     *
     * @param callable(T):bool   $predicate
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function dropWhile(callable $predicate, mixed $iterable): Generator
    {
        $dropping = true;
        foreach (self::toIterable($iterable) as $value) {
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
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function distinct(mixed $iterable): Generator
    {
        $hasher = new Hasher();
        $equalizer = new Equalizer();
        $seen = [];

        foreach (self::toIterable($iterable) as $value) {
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
     * @param iterable<T>|string $iterable
     *
     * @return Generator<int, T>
     */
    public static function dedupe(mixed $iterable): Generator
    {
        $equalizer = new Equalizer();
        $first = true;
        $prev = null;

        foreach (self::toIterable($iterable) as $value) {
            if ($first || !$equalizer->equals($value, $prev)) {
                yield $value;
                $prev = $value;
                $first = false;
            }
        }
    }

    /**
     * Partitions an iterable into chunks of size n.
     * Only yields complete partitions (drops incomplete final partition).
     *
     * @template T
     *
     * @param int                $n        The partition size
     * @param iterable<T>|string $iterable The input sequence
     *
     * @deprecated Use PartitionGenerator::partition() instead
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partition(int $n, mixed $iterable): Generator
    {
        return PartitionGenerator::partition($n, $iterable);
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
     * @deprecated Use PartitionGenerator::partitionAll() instead
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionAll(int $n, mixed $iterable): Generator
    {
        return PartitionGenerator::partitionAll($n, $iterable);
    }

    /**
     * @template T
     *
     * @param callable(T):mixed  $f
     * @param iterable<T>|string $iterable
     *
     * @deprecated Use PartitionGenerator::partitionBy() instead
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionBy(callable $f, mixed $iterable): Generator
    {
        return PartitionGenerator::partitionBy($f, $iterable);
    }

    /**
     * Lazily reads a file line by line.
     * Yields each line as a string with line endings removed.
     * Automatically closes the file handle when done or on error.
     *
     * @deprecated Use FileGenerator::fileLines() instead
     *
     * @return Generator<int, string>
     */
    public static function fileLines(string $filename): Generator
    {
        return FileGenerator::fileLines($filename);
    }

    /**
     * Lazily walks a directory tree, yielding file paths.
     * Returns all files and directories recursively.
     * Follows symbolic links but tracks visited inodes to prevent infinite cycles.
     *
     * @deprecated Use FileGenerator::fileSeq() instead
     *
     * @return Generator<int, string>
     */
    public static function fileSeq(string $path): Generator
    {
        return FileGenerator::fileSeq($path);
    }

    /**
     * Lazily reads a file in chunks of a specified size.
     * Yields byte strings of the specified chunk size (or smaller for the last chunk).
     * The file handle is automatically closed when the generator finishes or an error occurs.
     *
     * @param string $filename  The path to the file to read
     * @param int    $chunkSize The size of each chunk in bytes (default: 8192)
     *
     * @deprecated Use FileGenerator::readFileChunks() instead
     *
     * @return Generator<int, string>
     */
    public static function readFileChunks(string $filename, int $chunkSize = 8192): Generator
    {
        return FileGenerator::readFileChunks($filename, $chunkSize);
    }

    /**
     * Lazily reads a CSV file line by line.
     * Yields each row as a PersistentVector of string values.
     * The file handle is automatically closed when the generator finishes or an error occurs.
     *
     * @param string $filename  The path to the CSV file to read
     * @param string $separator The field separator (default: ',')
     * @param string $enclosure The field enclosure character (default: '"')
     * @param string $escape    The escape character (default: '\\')
     *
     * @deprecated Use FileGenerator::csvLines() instead
     *
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

    /**
     * Converts an iterable or string to an Iterator.
     *
     * @return Iterator<int|string, mixed>
     */
    private static function toIterator(mixed $value): Iterator
    {
        $iterable = self::toIterable($value);

        if ($iterable instanceof Iterator) {
            return $iterable;
        }

        if (is_array($iterable)) {
            return new ArrayIterator($iterable);
        }

        return (static fn () => yield from $iterable)();
    }
}
