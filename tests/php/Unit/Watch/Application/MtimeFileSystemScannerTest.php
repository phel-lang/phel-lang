<?php

declare(strict_types=1);

namespace PhelTest\Unit\Watch\Application;

use Phel\Watch\Application\MtimeFileSystemScanner;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;

final class MtimeFileSystemScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel-scanner-' . uniqid();
        mkdir($this->root . '/sub', 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
    }

    public function test_returns_empty_snapshot_when_no_phel_files(): void
    {
        file_put_contents($this->root . '/a.txt', 'hello');

        $scanner = new MtimeFileSystemScanner();
        $snapshot = $scanner->snapshot([$this->root]);

        self::assertSame([], $snapshot);
    }

    public function test_picks_up_phel_files_recursively(): void
    {
        file_put_contents($this->root . '/a.phel', '(ns a)');
        file_put_contents($this->root . '/sub/b.phel', '(ns b)');
        file_put_contents($this->root . '/sub/c.txt', 'ignored');

        $scanner = new MtimeFileSystemScanner();
        $snapshot = $scanner->snapshot([$this->root]);

        self::assertCount(2, $snapshot);
        self::assertArrayHasKey($this->root . '/a.phel', $snapshot);
        self::assertArrayHasKey($this->root . '/sub/b.phel', $snapshot);
    }

    public function test_picks_up_cljc_as_well_as_phel(): void
    {
        file_put_contents($this->root . '/a.phel', '(ns a)');
        file_put_contents($this->root . '/b.cljc', '(ns b)');

        $scanner = new MtimeFileSystemScanner();
        $snapshot = $scanner->snapshot([$this->root]);

        self::assertArrayHasKey($this->root . '/a.phel', $snapshot);
        self::assertArrayHasKey($this->root . '/b.cljc', $snapshot);
    }

    public function test_accepts_single_file_paths(): void
    {
        $path = $this->root . '/only.phel';
        file_put_contents($path, '(ns only)');

        $scanner = new MtimeFileSystemScanner();
        $snapshot = $scanner->snapshot([$path]);

        self::assertArrayHasKey($path, $snapshot);
    }

    public function test_silently_skips_missing_paths(): void
    {
        $scanner = new MtimeFileSystemScanner();
        $snapshot = $scanner->snapshot(['/does/not/exist']);

        self::assertSame([], $snapshot);
    }

    public function test_snapshot_includes_mtime_and_size(): void
    {
        $path = $this->root . '/sized.phel';
        file_put_contents($path, '123456');
        touch($path, 1_700_000_000);
        clearstatcache();

        $scanner = new MtimeFileSystemScanner();
        $snapshot = $scanner->snapshot([$this->root]);

        self::assertArrayHasKey($path, $snapshot);
        self::assertSame(6, $snapshot[$path]['size']);
        self::assertSame(1_700_000_000, $snapshot[$path]['mtime']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
