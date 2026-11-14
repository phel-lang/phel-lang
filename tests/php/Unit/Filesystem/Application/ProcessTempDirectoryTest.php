<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Application;

use DirectoryIterator;
use Phel\Filesystem\Application\FileIo;
use Phel\Filesystem\Application\ProcessTempDirectory;
use Phel\Filesystem\Application\TempDirFinder;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProcessTempDirectoryTest extends TestCase
{
    use RemoveDirTrait;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel-process-temp-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/eval', 0o700, true);
    }

    protected function tearDown(): void
    {
        ProcessTempDirectory::clearAll();
        $this->removeDir($this->root);
    }

    public function test_a_sweep_is_bounded_and_a_later_sweep_finishes_the_directory(): void
    {
        $dead = $this->managedDirectory();
        for ($index = 0; $index < 2050; ++$index) {
            file_put_contents($dead . '/__phel_' . md5((string) $index) . '.php', '');
        }

        $this->directory()->getOrCreate();

        self::assertCount(2, glob($dead . '/__phel_*.php') ?: []);
        for ($attempt = 0; $attempt < 4 && is_dir($dead); ++$attempt) {
            ProcessTempDirectory::clearAll();
            $this->directory()->getOrCreate();
        }

        self::assertDirectoryDoesNotExist($dead);
    }

    public function test_unknown_files_and_legacy_flat_files_are_preserved(): void
    {
        $legacy = $this->root . '/__phel_42_legacy.php';
        file_put_contents($legacy, 'legacy');
        $unknown = $this->managedDirectory() . '/user-file.txt';
        file_put_contents($unknown, 'user data');

        $this->directory()->getOrCreate();

        self::assertSame('legacy', file_get_contents($legacy));
        self::assertSame('user data', file_get_contents($unknown));
    }

    public function test_a_keep_marker_preserves_a_finished_debug_directory(): void
    {
        $kept = $this->managedDirectory();
        file_put_contents($kept . '/.keep', '');
        $filename = $kept . '/__phel_' . md5('debug') . '.php';
        file_put_contents($filename, 'debug source');

        $this->directory()->getOrCreate();

        self::assertSame('debug source', file_get_contents($filename));
    }

    public function test_a_managed_directory_symlink_is_never_followed(): void
    {
        $target = $this->root . '/outside';
        mkdir($target, 0o700);
        $filename = $target . '/__phel_' . md5('outside') . '.php';
        file_put_contents($filename, 'outside source');
        $alias = $this->root . '/eval/__phel_42_' . str_repeat('a', 32);
        if (!@symlink($target, $alias)) {
            self::markTestSkipped('Directory symlinks are unavailable.');
        }

        $this->directory()->getOrCreate();

        self::assertSame('outside source', file_get_contents($filename));
        self::assertTrue(is_link($alias));
    }

    public function test_an_interrupted_empty_creation_is_reclaimed(): void
    {
        $empty = $this->root . '/eval/__phel_42_' . str_repeat('a', 32);
        mkdir($empty, 0o700);

        $this->directory()->getOrCreate();

        self::assertDirectoryDoesNotExist($empty);
    }

    public function test_cleanup_defers_directory_removal_while_a_sweeper_holds_the_guard(): void
    {
        $directory = $this->directory()->getOrCreate();
        $filename = $directory . '/__phel_' . md5('owned') . '.php';
        file_put_contents($filename, 'owned source');
        $guard = fopen($this->root . '/eval/.sweep.lock', 'r+b');
        self::assertIsResource($guard);
        self::assertTrue(flock($guard, LOCK_EX));

        try {
            ProcessTempDirectory::clearAll();

            self::assertFileDoesNotExist($filename);
            self::assertDirectoryExists($directory);
            $lease = fopen($directory . '/.lock', 'r+b');
            self::assertIsResource($lease);
            self::assertTrue(flock($lease, LOCK_EX | LOCK_NB));
            fclose($lease);
        } finally {
            fclose($guard);
        }

        $this->directory()->getOrCreate();
        self::assertDirectoryDoesNotExist($directory);
    }

    public function test_later_starts_make_progress_past_a_full_page_of_kept_directories(): void
    {
        for ($index = 0; $index < 130; ++$index) {
            $path = $this->root . '/eval/__phel_42_' . md5((string) $index);
            mkdir($path, 0o700);
            file_put_contents($path . '/.lock', '');
        }

        file_put_contents($this->root . '/eval/.sweep.lock', '');
        $directories = [];
        foreach (new DirectoryIterator($this->root . '/eval') as $entry) {
            if (!$entry->isDot() && $entry->isDir()) {
                $directories[] = $entry->getPathname();
            }
        }

        $dead = array_pop($directories);
        self::assertIsString($dead);
        foreach ($directories as $directory) {
            file_put_contents($directory . '/.keep', '');
        }

        $filename = $dead . '/__phel_' . md5('dead') . '.php';
        file_put_contents($filename, 'dead owner');

        $this->directory()->getOrCreate();
        self::assertFileExists($filename);
        for ($attempt = 0; $attempt < 4 && is_dir($dead); ++$attempt) {
            ProcessTempDirectory::clearAll();
            $this->directory()->getOrCreate();
        }

        self::assertDirectoryDoesNotExist($dead);
    }

    #[DataProvider('invalidCursors')]
    public function test_an_invalid_or_obsolete_cursor_restarts_the_sweep(string $cursor): void
    {
        $dead = $this->managedDirectory();
        file_put_contents($dead . '/__phel_' . md5('dead') . '.php', '');
        file_put_contents($this->root . '/eval/.sweep.lock', $cursor);

        $this->directory()->getOrCreate();

        self::assertDirectoryDoesNotExist($dead);
    }

    public static function invalidCursors(): iterable
    {
        yield 'invalid' => ["not an offset\n"];
        yield 'truncated' => ['42'];
        yield 'negative' => ["-1\n"];
        yield 'overflow' => [str_repeat('9', 30) . "\n"];
        yield 'past end after deletions' => ["100000\n"];
    }

    private function managedDirectory(): string
    {
        $path = $this->root . '/eval/__phel_42_' . str_repeat('a', 32);
        mkdir($path, 0o700);
        file_put_contents($path . '/.lock', '');

        return $path;
    }

    private function directory(): ProcessTempDirectory
    {
        return new ProcessTempDirectory(new TempDirFinder(new FileIo(), $this->root), false);
    }
}
