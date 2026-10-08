<?php

declare(strict_types=1);

namespace Phel\Lang\Generators;

use FilesystemIterator;
use Generator;
use InvalidArgumentException;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\TypeFactory;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use UnexpectedValueException;

use function is_file;
use function rtrim;

final class FileGenerator
{
    /**
     * @return Generator<int, string>
     */
    public static function fileLines(string $filename): Generator
    {
        $handle = self::openForReading($filename, 'r');

        try {
            while (($line = fgets($handle)) !== false) {
                yield rtrim($line, "\r\n");
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Follows symbolic links, but lists each real file or directory once and
     * never descends into a directory it has already entered, so a symlink
     * back to an ancestor ends the walk instead of looping until the path is
     * too long.
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

        if (is_file($path)) {
            yield $path;
            return;
        }

        if (is_dir($path)) {
            $visited = [];
            self::firstVisit($path, $visited);

            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveCallbackFilterIterator(
                        new RecursiveDirectoryIterator(
                            $path,
                            FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS,
                        ),
                        static function (mixed $current, string $pathname) use (&$visited): bool {
                            return self::firstVisit($pathname, $visited);
                        },
                    ),
                    RecursiveIteratorIterator::SELF_FIRST,
                );

                foreach ($iterator as $fileInfo) {
                    if ($fileInfo instanceof SplFileInfo) {
                        yield $fileInfo->getPathname();
                    }
                }
            } catch (UnexpectedValueException $e) {
                throw new RuntimeException('Error reading directory: ' . $path . ' - ' . $e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * @return Generator<int, string>
     */
    public static function readFileChunks(string $filename, int $chunkSize = 8192): Generator
    {
        if ($chunkSize <= 0) {
            throw new InvalidArgumentException(
                'Chunk size must be positive, got: ' . $chunkSize,
            );
        }

        $handle = self::openForReading($filename, 'rb');

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
     * @return Generator<int, PersistentVectorInterface<mixed>>
     */
    public static function csvLines(
        string $filename,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
    ): Generator {
        $handle = self::openForReading($filename, 'r');

        try {
            $typeFactory = TypeFactory::getInstance();
            while (($row = fgetcsv($handle, 0, $separator, $enclosure, $escape)) !== false) {
                /** @psalm-var list<string|null> $row */
                $cleanRow = array_map(static fn(?string $val): string => $val ?? '', $row);
                yield $typeFactory->persistentVectorFromArray($cleanRow);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Whether `$path` resolves to a file or directory not seen yet, marking it
     * seen. A path `stat` cannot follow, such as a dangling symlink, counts as
     * new.
     *
     * @param array<string, true> $visited
     */
    private static function firstVisit(string $path, array &$visited): bool
    {
        $stat = @stat($path);
        if ($stat === false) {
            return true;
        }

        $inode = $stat['dev'] . ':' . $stat['ino'];
        if (isset($visited[$inode])) {
            return false;
        }

        $visited[$inode] = true;

        return true;
    }

    /**
     * Validates that `$filename` names a readable file and opens it, so every
     * reader in this class rejects bad input with the same messages.
     *
     * @return resource
     */
    private static function openForReading(string $filename, string $mode)
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

        $handle = fopen($filename, $mode);
        if ($handle === false) {
            throw new RuntimeException(
                'Failed to open file: ' . $filename,
            );
        }

        return $handle;
    }
}
