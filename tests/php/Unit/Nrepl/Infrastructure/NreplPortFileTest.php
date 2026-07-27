<?php

declare(strict_types=1);

namespace PhelTest\Unit\Nrepl\Infrastructure;

use Phel\Nrepl\Infrastructure\NreplPortFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;

use const DIRECTORY_SEPARATOR;

final class NreplPortFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('phel-nrepl-port-', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        new NreplPortFile($this->dir)->delete();
        rmdir($this->dir);
    }

    public function test_write_creates_dot_nrepl_port_with_the_port(): void
    {
        $portFile = new NreplPortFile($this->dir);

        $portFile->write(7888);

        self::assertFileExists($portFile->path());
        self::assertSame($this->dir . DIRECTORY_SEPARATOR . '.nrepl-port', $portFile->path());
        self::assertSame('7888', file_get_contents($portFile->path()));
    }

    public function test_write_overwrites_a_previous_port(): void
    {
        $portFile = new NreplPortFile($this->dir);

        $portFile->write(7888);
        $portFile->write(40123);

        self::assertSame('40123', file_get_contents($portFile->path()));
    }

    public function test_delete_removes_the_file(): void
    {
        $portFile = new NreplPortFile($this->dir);
        $portFile->write(7888);

        $portFile->delete();

        self::assertFileDoesNotExist($portFile->path());
    }

    public function test_delete_is_a_no_op_when_no_file_exists(): void
    {
        $portFile = new NreplPortFile($this->dir);

        $portFile->delete();
        $portFile->delete();

        self::assertFileDoesNotExist($portFile->path());
    }

    public function test_write_throws_when_the_directory_is_missing(): void
    {
        $portFile = new NreplPortFile($this->dir . DIRECTORY_SEPARATOR . 'missing');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot write .nrepl-port');

        $portFile->write(7888);
    }
}
