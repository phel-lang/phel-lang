<?php

declare(strict_types=1);

namespace Phel\Filesystem\Infrastructure;

use Phel\Filesystem\Domain\FilesystemInterface;

final class FilesystemSingleton implements FilesystemInterface
{
    /** @var list<string> */
    private static array $files = [];

    public function __construct(
        private bool $shouldKeepGeneratedTempFiles,
    ) {
    }

    public static function reset(): void
    {
        self::$files = [];
    }

    public function addFile(string $file): void
    {
        self::$files[] = $file;
    }

    public function clearAll(): array
    {
        $files = [];
        if (!$this->shouldKeepGeneratedTempFiles) {
            foreach (self::$files as $file) {
                $files[] = $file;
                unlink($file);
            }
        }
        self::$files = [];
        return $files;
    }
}
