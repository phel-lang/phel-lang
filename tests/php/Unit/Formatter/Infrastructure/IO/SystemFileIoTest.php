<?php

declare(strict_types=1);

namespace PhelTest\Unit\Formatter\Infrastructure\IO;

use Phel\Formatter\Infrastructure\IO\SystemFileIo;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function bin2hex;
use function chmod;
use function file_get_contents;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

final class SystemFileIoTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/phel-file-io-' . bin2hex(random_bytes(6));
        mkdir($this->baseDir, 0755, true);
    }

    protected function tearDown(): void
    {
        @chmod($this->baseDir, 0755);
        @unlink($this->baseDir . '/out.phel');
        @rmdir($this->baseDir);
    }

    public function test_put_contents_writes_the_file(): void
    {
        new SystemFileIo()->putContents($this->baseDir . '/out.phel', '(ns out)');

        self::assertSame('(ns out)', file_get_contents($this->baseDir . '/out.phel'));
    }

    public function test_put_contents_throws_when_target_is_not_writable(): void
    {
        chmod($this->baseDir, 0555);
        if (@file_put_contents($this->baseDir . '/probe', 'x') !== false) {
            @unlink($this->baseDir . '/probe');
            self::markTestSkipped('chmod has no effect (running as root?)');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to write file');

        new SystemFileIo()->putContents($this->baseDir . '/out.phel', '(ns out)');
    }
}
