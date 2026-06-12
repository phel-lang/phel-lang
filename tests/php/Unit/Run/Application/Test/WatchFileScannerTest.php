<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\WatchFileScanner;
use PHPUnit\Framework\TestCase;

final class WatchFileScannerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phel-watch-scanner-' . bin2hex(random_bytes(6));
        mkdir($this->dir . '/nested', 0755, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_collects_phel_files_and_config_recursively(): void
    {
        touch($this->dir . '/a.phel');
        touch($this->dir . '/nested/b.phel');
        touch($this->dir . '/phel-config.php');
        touch($this->dir . '/ignored.php');
        touch($this->dir . '/ignored.txt');

        $snapshot = new WatchFileScanner()->snapshot([$this->dir]);

        self::assertSame(
            [
                $this->dir . '/a.phel',
                $this->dir . '/nested/b.phel',
                $this->dir . '/phel-config.php',
            ],
            array_keys($snapshot),
        );
    }

    public function test_snapshot_changes_when_a_file_is_touched(): void
    {
        $file = $this->dir . '/a.phel';
        touch($file, time() - 100);
        $scanner = new WatchFileScanner();

        $before = $scanner->snapshot([$this->dir]);
        touch($file, time());
        $after = $scanner->snapshot([$this->dir]);

        self::assertNotSame($before, $after);
    }

    public function test_missing_directory_is_skipped(): void
    {
        self::assertSame([], new WatchFileScanner()->snapshot([$this->dir . '/does-not-exist']));
    }
}
