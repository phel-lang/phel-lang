<?php

declare(strict_types=1);

namespace Phel\Filesystem\Infrastructure;

use Phel\Filesystem\Application\ProcessTempDirectory;
use Phel\Filesystem\Domain\FilesystemInterface;

/**
 * @internal
 */
final class RealFilesystem implements FilesystemInterface
{
    /** @var array<int, list<string>> */
    private static array $files = [];

    private static bool $cleanupRegistered = false;

    public static function reset(): void
    {
        unset(self::$files[getmypid() ?: 0]);
    }

    public function addFile(string $file): void
    {
        if (!self::$cleanupRegistered) {
            register_shutdown_function(static function (): void {
                new self()->clearAll();
            });
            self::$cleanupRegistered = true;
        }

        self::$files[getmypid() ?: 0][] = $file;
    }

    public function clearAll(): void
    {
        foreach (self::$files[getmypid() ?: 0] ?? [] as $file) {
            // $files is process-global static, so a path may already be gone
            // (cleared by another run or never written) — skip rather than warn.
            if (file_exists($file)) {
                unlink($file);
            }
        }

        self::reset();
        ProcessTempDirectory::clearAll();
    }
}
