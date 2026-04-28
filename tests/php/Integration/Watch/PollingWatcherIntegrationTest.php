<?php

declare(strict_types=1);

namespace PhelTest\Integration\Watch;

use Phel\Watch\Application\MtimeFileSystemScanner;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;

/**
 * Integration-style coverage of the polling-backend primitives without
 * depending on wall-clock timing. The deterministic behaviour of
 * `PollingWatcher` itself is covered by the unit tests with a fake clock;
 * here we sanity-check the real scanner against a real filesystem.
 */
final class PollingWatcherIntegrationTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phel-watch-' . uniqid('', true);
        @mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->tmpDir);
    }

    public function test_scanner_detects_mtime_or_size_change(): void
    {
        $file = $this->tmpDir . '/app.phel';
        file_put_contents($file, "(ns app)\n");
        touch($file, time() - 5);

        $scanner = new MtimeFileSystemScanner();
        $before = $scanner->snapshot([$this->tmpDir]);
        self::assertArrayHasKey($file, $before);

        file_put_contents($file, "(ns app)\n;; changed\n");
        touch($file, time() + 5);

        $after = $scanner->snapshot([$this->tmpDir]);
        self::assertArrayHasKey($file, $after);
        self::assertNotSame(
            [$before[$file]['mtime'], $before[$file]['size']],
            [$after[$file]['mtime'], $after[$file]['size']],
        );
    }

    public function test_scanner_only_reports_phel_files(): void
    {
        file_put_contents($this->tmpDir . '/ignored.txt', 'plain text');
        file_put_contents($this->tmpDir . '/keep.phel', "(ns keep)\n");

        $snapshot = new MtimeFileSystemScanner()->snapshot([$this->tmpDir]);
        $paths = array_keys($snapshot);

        self::assertCount(1, $paths);
        self::assertStringEndsWith('keep.phel', $paths[0]);
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = @scandir($dir) ?: [];
        foreach ($files as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->cleanDir($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
