<?php

declare(strict_types=1);

namespace Phel\Filesystem\Infrastructure;

use Phel\Filesystem\Domain\FilesystemInterface;

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
