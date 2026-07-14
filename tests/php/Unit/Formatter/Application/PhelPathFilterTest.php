<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Application;

use Phel\Formatter\Application\PhelPathFilter;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function chmod;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class PhelPathFilterTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/phel-path-filter-' . bin2hex(random_bytes(6));
        mkdir($this->baseDir . '/readable', 0755, true);
        mkdir($this->baseDir . '/unreadable', 0755, true);
        file_put_contents($this->baseDir . '/readable/a.phel', '(ns a)');
        file_put_contents($this->baseDir . '/unreadable/hidden.phel', '(ns hidden)');
        chmod($this->baseDir . '/unreadable', 0000);
    }

    protected function tearDown(): void
    {
        @chmod($this->baseDir . '/unreadable', 0755);
        @unlink($this->baseDir . '/unreadable/hidden.phel');
        @unlink($this->baseDir . '/readable/a.phel');
        @rmdir($this->baseDir . '/unreadable');
        @rmdir($this->baseDir . '/readable');
        @rmdir($this->baseDir);
    }

    public function test_unreadable_subdirectory_is_skipped_not_fatal(): void
    {
        if (is_readable($this->baseDir . '/unreadable')) {
            self::markTestSkipped('chmod has no effect (running as root?)');
        }

        $paths = new PhelPathFilter()->filterPaths([$this->baseDir]);

        self::assertSame([$this->baseDir . '/readable/a.phel'], $paths);
    }
}
