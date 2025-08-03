<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Application;

use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Filesystem\Application\TempDirFinder;
use Phel\Filesystem\Domain\FileIoInterface;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class TempDirFinderTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_throws_exception_when_dir_not_writable(): void
    {
        $dir = sys_get_temp_dir() . '/phel-unwritable-' . uniqid('', true);
        mkdir($dir);

        $fileIo = self::createStub(FileIoInterface::class);
        $fileIo->method('isWritable')->willReturn(false);

        $finder = new TempDirFinder($fileIo, $dir);

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Directory is not writable: ' . $dir);

        try {
            $finder->getOrCreateTempDir();
        } finally {
            rmdir($dir);
        }
    }

    #[RunInSeparateProcess]
    public function test_makes_dir_writable_when_possible(): void
    {
        $dir = sys_get_temp_dir() . '/phel-unwritable-' . uniqid('', true);
        mkdir($dir);
        chmod($dir, 0555);

        $fileIo = $this->createMock(FileIoInterface::class);
        $fileIo->expects(self::exactly(2))
            ->method('isWritable')
            ->with($dir)
            ->willReturnOnConsecutiveCalls(false, true);

        $finder = new TempDirFinder($fileIo, $dir);

        self::assertSame($dir, $finder->getOrCreateTempDir());

        chmod($dir, 0755);
        rmdir($dir);
    }
}
