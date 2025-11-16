<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use FilesystemIterator;
use Generator;
use InvalidArgumentException;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\TypeFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use UnexpectedValueException;

use function is_file;
use function rtrim;

/**
 * File I/O generators for lazy file operations.
 * Provides generators for reading files line-by-line, walking directories,
 * reading chunks, and parsing CSV files.
 */
final class FileGenerators
{
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
}
