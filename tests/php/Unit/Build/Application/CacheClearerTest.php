<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use InvalidArgumentException;
use Phel\Build\Application\CacheClearer;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function filesystemRoots(): iterable
    {
        yield 'drive backslash' => ['D:\\'];
        yield 'drive slash' => ['D:/'];
        yield 'UNC share' => ['\\\\server\\share'];
        yield 'UNC share slash' => ['//server/share/'];
        yield 'extended drive' => ['\\\\?\\D:\\'];
        yield 'extended UNC share' => ['\\\\?\\UNC\\server\\share\\'];
    }

    #[DataProvider('filesystemRoots')]
    public function test_refuses_filesystem_roots_on_other_drives(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CacheClearer($path, $this->root . '/cache', $this->root . '/opcache')->clearAll();
    }

    public function test_refuses_a_project_directory_before_clearing_other_caches(): void
    {
        file_put_contents($this->root . '/phel-config.php', '<?php return [];');

        try {
            new CacheClearer($this->root . '/tmp', $this->root, $this->root . '/opcache')->clearAll();
            self::fail('Expected the project directory to be refused');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('Refusing to clear', $invalidArgumentException->getMessage());
            self::assertFileExists($this->root . '/phel-config.php');
            self::assertFileExists($this->root . '/tmp/__phel_1.php');
        }
    }

    public function test_refuses_the_current_directory_and_its_ancestor(): void
    {
        $cwd = getcwd();
        chdir($this->root . '/tmp');
        try {
            foreach (['.', '..', '../cache/..'] as $unsafe) {
                try {
                    new CacheClearer($unsafe, $this->root . '/cache', $this->root . '/opcache')->clearAll();
                    self::fail('Expected unsafe directory to be refused: ' . $unsafe);
                } catch (InvalidArgumentException) {
                    self::assertFileExists($this->root . '/tmp/__phel_1.php');
                    self::assertFileExists($this->root . '/cache/index.php');
                }
            }
        } finally {
            chdir($cwd);
        }
    }

    public function test_refuses_an_explicit_project_root_outside_the_working_directory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CacheClearer($this->root, $this->root . '/cache', $this->root . '/opcache', $this->root . '/tmp')->clearAll();
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

    public function test_a_directory_reached_through_a_symlinked_parent_is_still_cleared(): void
    {
        // A link above the configured dir is part of the path the user chose
        // (macOS reaches its temp dir through /var -> /private/var); only a
        // link at the configured dir itself is refused.
        mkdir($this->root . '/real/cache', 0o755, true);
        file_put_contents($this->root . '/real/cache/index.php', '<?php');
        symlink($this->root . '/real', $this->root . '/via');

        new CacheClearer($this->root . '/tmp', $this->root . '/via/cache', $this->root . '/opcache')->clearAll();

        self::assertDirectoryDoesNotExist($this->root . '/real/cache');
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
