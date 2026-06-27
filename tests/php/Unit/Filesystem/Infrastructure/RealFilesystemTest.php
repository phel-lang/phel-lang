<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Infrastructure;

use Phel\Filesystem\Infrastructure\RealFilesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use const E_WARNING;

final class RealFilesystemTest extends TestCase
{
    protected function setUp(): void
    {
        RealFilesystem::reset();
    }

    protected function tearDown(): void
    {
        RealFilesystem::reset();
    }

    public function test_clear_all_removes_all_tracked_files(): void
    {
        $fileA = $this->createTempFile();
        $fileB = $this->createTempFile();

        $filesystem = new RealFilesystem();
        $filesystem->addFile($fileA);
        $filesystem->addFile($fileB);

        $filesystem->clearAll();

        self::assertFileDoesNotExist($fileA);
        self::assertFileDoesNotExist($fileB);
    }

    public function test_clear_all_resets_tracking_array(): void
    {
        $file = $this->createTempFile();

        $filesystem = new RealFilesystem();
        $filesystem->addFile($file);
        $filesystem->clearAll();

        // Re-create the same path; a second clearAll must NOT delete it,
        // proving the tracking array was reset after the first call.
        file_put_contents($file, 'kept');
        $filesystem->clearAll();

        self::assertFileExists($file);

        unlink($file);
    }

    public function test_clear_all_skips_already_removed_paths_without_warning(): void
    {
        $present = $this->createTempFile();
        $alreadyGone = $this->createTempFile();
        // Simulate a path a prior run already deleted: $files is process-global static.
        unlink($alreadyGone);

        $filesystem = new RealFilesystem();
        $filesystem->addFile($alreadyGone);
        $filesystem->addFile($present);

        set_error_handler(static function (int $severity, string $message): bool {
            throw new RuntimeException('Unexpected PHP warning: ' . $message);
        }, E_WARNING);

        try {
            $filesystem->clearAll();
        } finally {
            restore_error_handler();
        }

        // No warning fired for the missing path, and present files were still cleaned.
        self::assertFileDoesNotExist($present);
    }

    public function test_static_state_is_shared_across_instances(): void
    {
        $file = $this->createTempFile();

        $registrar = new RealFilesystem();
        $registrar->addFile($file);

        // A different instance shares the same static $files array.
        $cleaner = new RealFilesystem();
        $cleaner->clearAll();

        self::assertFileDoesNotExist($file);
    }

    private function createTempFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'phel-realfs-');
        self::assertIsString($file);
        self::assertFileExists($file);

        return $file;
    }
}
