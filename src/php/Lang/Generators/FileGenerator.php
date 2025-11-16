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

final class FileGenerator
{
    /**
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

        if (is_file($path)) {
            yield $path;
            return;
        }

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
                    $realPath = $fileInfo->getRealPath();

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
                /** @psalm-var list<string|null> $row */
                $cleanRow = array_map(static fn (?string $val): string => $val ?? '', $row);
                yield $typeFactory->persistentVectorFromArray($cleanRow);
            }
        } finally {
            fclose($handle);
        }
    }
}
