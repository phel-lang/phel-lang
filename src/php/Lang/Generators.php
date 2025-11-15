<?php

declare(strict_types=1);

namespace Phel\Lang;

use ArrayIterator;
use FilesystemIterator;
use Generator;
use InvalidArgumentException;
use Iterator;
use Phel;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use UnexpectedValueException;

use function count;
use function is_array;
use function is_string;
use function mb_str_split;

final class Generators
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
     * Maps a function over an iterable with index.
     * Applies the function to each element along with its index (0-based).
     *
     * @template T
     * @template U
     *
     * @param callable(int, T): U $f        The mapping function that takes index and value
     * @param iterable<T>         $iterable The input sequence
     *
     * @return Generator<int, U>
     */
    public static function mapIndexed(callable $f, iterable $iterable): Generator
    {
        $index = 0;
        foreach ($iterable as $value) {
            yield $f($index, $value);
            ++$index;
        }
    }

    /**
     * Interleaves multiple iterables.
     * Returns elements by taking one from each iterable in turn.
     * Continues until the first iterable is exhausted, padding others with null.
     *
     * @template T
     *
     * @param iterable<T> ...$iterables The sequences to interleave
     *
     * @return Generator<int, T|null>
     */
    public static function interleave(iterable ...$iterables): Generator
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
     * @template T
     *
     * @param callable    $f            The mapping function
     * @param iterable<T> ...$iterables The sequences to map over
     *
     * @return Generator<int, mixed>
     */
    public static function mapMulti(callable $f, iterable ...$iterables): Generator
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
     * Partitions an iterable into chunks of size n.
     * Only yields complete partitions (drops incomplete final partition).
     *
     * @template T
     *
     * @param int         $n        The partition size
     * @param iterable<T> $iterable The input sequence
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partition(int $n, iterable $iterable): Generator
    {
        if ($n <= 0) {
            return;
        }

        $partition = [];
        foreach ($iterable as $value) {
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
     * @param int         $n        The partition size
     * @param iterable<T> $iterable The input sequence
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function partitionAll(int $n, iterable $iterable): Generator
    {
        if ($n <= 0) {
            return;
        }

        $partition = [];
        foreach ($iterable as $value) {
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

    /**
     * Lazily reads a file line by line.
     * Yields each line as a string with line endings removed.
     * Automatically closes the file handle when done or on error.
     *
     * @return Generator<int, string>
     */
    public static function fileLines(string $filename): Generator
    {
        if (!is_file($filename)) {
            throw new InvalidArgumentException(
                'Argument filename should be a valid path to a file: ' . $filename,
            );
        }

        if (!is_readable($filename)) {
            throw new InvalidArgumentException(
                'File is not readable: ' . $filename,
            );
        }

        $handle = fopen($filename, 'r');
        if ($handle === false) {
            throw new RuntimeException(
                'Failed to open file: ' . $filename,
            );
        }

        try {
            while (($line = fgets($handle)) !== false) {
                yield rtrim($line, "\r\n");
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Lazily walks a directory tree, yielding file paths.
     * Returns all files and directories recursively.
     * Follows symbolic links but tracks visited inodes to prevent infinite cycles.
     *
     * @return Generator<int, string>
     */
    public static function fileSeq(string $path): Generator
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(
                'Path does not exist: ' . $path,
            );
        }

        if (!is_readable($path)) {
            throw new InvalidArgumentException(
                'Path is not readable: ' . $path,
            );
        }

        // If it's a file, just yield it
        if (is_file($path)) {
            yield $path;
            return;
        }

        // If it's a directory, walk it recursively with cycle detection
        if (is_dir($path)) {
            $visited = [];

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $path,
                        FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS,
                    ),
                    RecursiveIteratorIterator::SELF_FIRST,
                );

                foreach ($iterator as $fileInfo) {
                    $pathname = $fileInfo->getPathname();

                    // Get real path to detect cycles via inode tracking
                    $realPath = $fileInfo->getRealPath();

                    // Skip if we've already visited this inode (cycle detection)
                    if ($realPath !== false) {
                        $stat = @stat($realPath);
                        if ($stat !== false) {
                            $inode = $stat['dev'] . ':' . $stat['ino'];

                            if (isset($visited[$inode])) {
                                continue;
                            }

                            $visited[$inode] = true;
                        }
                    }

                    yield $pathname;
                }
            } catch (UnexpectedValueException $e) {
                throw new RuntimeException('Error reading directory: ' . $path . ' - ' . $e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * Lazily reads a file in chunks of a specified size.
     * Yields byte strings of the specified chunk size (or smaller for the last chunk).
     * The file handle is automatically closed when the generator finishes or an error occurs.
     *
     * @param string $filename  The path to the file to read
     * @param int    $chunkSize The size of each chunk in bytes (default: 8192)
     *
     * @throws InvalidArgumentException if the file doesn't exist, is not readable, or chunk size is invalid
     * @throws RuntimeException         if the file cannot be opened
     *
     * @return Generator<int, string>
     */
    public static function readFileChunks(string $filename, int $chunkSize = 8192): Generator
    {
        if ($chunkSize <= 0) {
            throw new InvalidArgumentException(
                'Chunk size must be positive, got: ' . $chunkSize,
            );
        }

        if (!is_file($filename)) {
            throw new InvalidArgumentException(
                'Argument filename should be a valid path to a file: ' . $filename,
            );
        }

        if (!is_readable($filename)) {
            throw new InvalidArgumentException(
                'File is not readable: ' . $filename,
            );
        }

        $handle = fopen($filename, 'rb');
        if ($handle === false) {
            throw new RuntimeException(
                'Failed to open file: ' . $filename,
            );
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException(
                        'Failed to read from file: ' . $filename,
                    );
                }

                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        } finally {
            fclose($handle);
        }
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
     * @throws InvalidArgumentException if the file doesn't exist or is not readable
     * @throws RuntimeException         if the file cannot be opened
     *
     * @return Generator<int, PersistentVectorInterface>
     */
    public static function csvLines(
        string $filename,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
    ): Generator {
        if (!is_file($filename)) {
            throw new InvalidArgumentException(
                'Argument filename should be a valid path to a file: ' . $filename,
            );
        }

        if (!is_readable($filename)) {
            throw new InvalidArgumentException(
                'File is not readable: ' . $filename,
            );
        }

        $handle = fopen($filename, 'r');
        if ($handle === false) {
            throw new RuntimeException(
                'Failed to open file: ' . $filename,
            );
        }

        try {
            $typeFactory = TypeFactory::getInstance();
            while (($row = fgetcsv($handle, 0, $separator, $enclosure, $escape)) !== false) {
                // fgetcsv returns list<string|null>|false
                // Convert null values to empty strings for consistency
                /** @psalm-var list<string|null> $row */
                $cleanRow = array_map(static fn (?string $val): string => $val ?? '', $row);
                yield $typeFactory->persistentVectorFromArray($cleanRow);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Converts an iterable to an Iterator.
     *
     * @template T
     *
     * @param iterable<T> $iterable
     *
     * @return Iterator<int|string, T>
     */
    private static function toIterator(iterable $iterable): Iterator
    {
        if ($iterable instanceof Iterator) {
            return $iterable;
        }

        if (is_array($iterable)) {
            return new ArrayIterator($iterable);
        }

        return (static fn () => yield from $iterable)();
    }
}
