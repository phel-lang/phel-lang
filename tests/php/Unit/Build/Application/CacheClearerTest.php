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
