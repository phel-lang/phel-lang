<?php

declare(strict_types=1);

namespace Phel\Filesystem\Infrastructure;

use Phel\Filesystem\Domain\FilesystemInterface;

/**
 * Tracks compiled files in a process-local static array and deletes them
 * via unlink() on clearAll().
 *
 * Note: the tracking array is static, so it is shared across every instance:
 * addFile() and clearAll() operate on the same global state regardless of the
 * instance they are called on. reset() clears the array and exists primarily
 * to isolate tests.
 *
 * NullFilesystem is used instead when KEEP_GENERATED_TEMP_FILES is true.
 */
final class RealFilesystem implements FilesystemInterface
{
    /** @var list<string> */
    private static array $files = [];

    public static function reset(): void
    {
        self::$files = [];
    }

    public function addFile(string $file): void
    {
        self::$files[] = $file;
    }

    public function clearAll(): void
    {
        foreach (self::$files as $file) {
            unlink($file);
        }

        self::reset();
    }
}
