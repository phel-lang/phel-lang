<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\CacheClearer;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class CacheClearerTest extends TestCase
{
    use RemoveDirTrait;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel-cache-clearer-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/tmp', 0o755, true);
        mkdir($this->root . '/cache', 0o755, true);
        mkdir($this->root . '/opcache/a0d131c96acbcfdc5ebf8e9b6e5ff55a/opt', 0o755, true);

        file_put_contents($this->root . '/tmp/__phel_1.php', '<?php');
        file_put_contents($this->root . '/cache/index.php', '<?php');
        file_put_contents($this->root . '/opcache/a0d131c96acbcfdc5ebf8e9b6e5ff55a/opt/app.php.bin', 'bin');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function test_clears_every_configured_directory(): void
    {
        $cleared = new CacheClearer(
            $this->root . '/tmp',
            $this->root . '/cache',
            $this->root . '/opcache',
        )->clearAll();

        self::assertSame([
            $this->root . '/tmp',
            $this->root . '/cache',
            $this->root . '/opcache',
        ], $cleared);
        self::assertDirectoryDoesNotExist($this->root . '/tmp');
        self::assertDirectoryDoesNotExist($this->root . '/cache');
    }

    public function test_a_symlinked_directory_loses_the_link_and_keeps_its_target(): void
    {
        mkdir($this->root . '/elsewhere');
        file_put_contents($this->root . '/elsewhere/keep.txt', 'mine');
        $this->removeDir($this->root . '/tmp');
        symlink($this->root . '/elsewhere', $this->root . '/tmp');

        new CacheClearer($this->root . '/tmp', $this->root . '/cache', $this->root . '/opcache')->clearAll();

        self::assertFileExists($this->root . '/elsewhere/keep.txt');
        self::assertFalse(is_link($this->root . '/tmp'));
    }

    public function test_a_symlinked_opcache_directory_is_left_alone(): void
    {
        mkdir($this->root . '/elsewhere');
        file_put_contents($this->root . '/elsewhere/keep.txt', 'mine');
        $this->removeDir($this->root . '/opcache');
        symlink($this->root . '/elsewhere', $this->root . '/opcache');

        $cleared = new CacheClearer($this->root . '/tmp', $this->root . '/cache', $this->root . '/opcache')->clearAll();

        self::assertFileExists($this->root . '/elsewhere/keep.txt');
        self::assertTrue(is_link($this->root . '/opcache'));
        self::assertNotContains($this->root . '/opcache', $cleared);
    }

    public function test_leaves_the_opcache_directory_present_and_empty(): void
    {
        // PHP aborts at startup when opcache.file_cache points at a missing
        // path, so this one is emptied in place instead of removed.
        new CacheClearer(
            $this->root . '/tmp',
            $this->root . '/cache',
            $this->root . '/opcache',
        )->clearAll();

        self::assertDirectoryExists($this->root . '/opcache');
        self::assertDirectoryDoesNotExist($this->root . '/opcache/a0d131c96acbcfdc5ebf8e9b6e5ff55a');
    }

    public function test_skips_directories_that_do_not_exist(): void
    {
        $cleared = new CacheClearer(
            $this->root . '/absent-tmp',
            $this->root . '/cache',
            $this->root . '/absent-opcache',
        )->clearAll();

        self::assertSame([$this->root . '/cache'], $cleared);
    }
}
