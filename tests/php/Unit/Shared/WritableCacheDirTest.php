<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\WritableCacheDir;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function chmod;
use function is_writable;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;

final class WritableCacheDirTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        WritableCacheDir::reset();
        $this->baseDir = sys_get_temp_dir() . '/phel-writable-' . bin2hex(random_bytes(6));
        mkdir($this->baseDir, 0755, true);
    }

    protected function tearDown(): void
    {
        WritableCacheDir::reset();
        @chmod($this->baseDir, 0755);
        @rmdir($this->baseDir . '/nested/cache');
        @rmdir($this->baseDir . '/nested');
        @rmdir($this->baseDir);
    }

    public function test_existing_writable_dir_is_usable(): void
    {
        self::assertTrue(WritableCacheDir::isUsable($this->baseDir));
    }

    public function test_missing_dir_under_writable_parent_is_created_and_usable(): void
    {
        $dir = $this->baseDir . '/nested/cache';

        self::assertTrue(WritableCacheDir::isUsable($dir));
        self::assertDirectoryExists($dir);
    }

    public function test_dir_under_read_only_parent_is_not_usable(): void
    {
        chmod($this->baseDir, 0555);
        if (is_writable($this->baseDir . '/probe') || @mkdir($this->baseDir . '/probe')) {
            @rmdir($this->baseDir . '/probe');
            self::markTestSkipped('chmod has no effect (running as root?)');
        }

        self::assertFalse(WritableCacheDir::isUsable($this->baseDir . '/nested/cache'));
        self::assertDirectoryDoesNotExist($this->baseDir . '/nested/cache');
    }

    public function test_read_only_existing_dir_is_not_usable(): void
    {
        chmod($this->baseDir, 0555);
        if (@mkdir($this->baseDir . '/probe')) {
            @rmdir($this->baseDir . '/probe');
            self::markTestSkipped('chmod has no effect (running as root?)');
        }

        self::assertFalse(WritableCacheDir::isUsable($this->baseDir));
    }

    public function test_empty_dir_is_not_usable(): void
    {
        self::assertFalse(WritableCacheDir::isUsable(''));
    }

    public function test_result_is_memoized_until_reset(): void
    {
        $dir = $this->baseDir . '/nested/cache';
        self::assertTrue(WritableCacheDir::isUsable($dir));

        rmdir($dir);
        rmdir($this->baseDir . '/nested');
        self::assertTrue(WritableCacheDir::isUsable($dir), 'memoized answer survives dir removal');

        WritableCacheDir::reset();
        self::assertTrue(WritableCacheDir::isUsable($dir), 're-created after reset');
        self::assertDirectoryExists($dir);
    }
}
