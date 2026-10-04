<?php

declare(strict_types=1);

namespace PhelTest\Unit\Filesystem\Application;

use Phel\Filesystem\Application\TempDirFinder;
use Phel\Filesystem\Domain\DirectoryWritabilityCheckerInterface;
use Phel\Shared\Exceptions\FileException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function function_exists;

final class TempDirFinderTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_throws_exception_when_dir_not_writable(): void
    {
        $dir = sys_get_temp_dir() . '/phel-unwritable-' . uniqid('', true);
        mkdir($dir);

        $fileIo = self::createStub(DirectoryWritabilityCheckerInterface::class);
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
    public function test_makes_dir_writable_for_its_owner_when_possible(): void
    {
        $dir = sys_get_temp_dir() . '/phel-unwritable-' . uniqid('', true);
        mkdir($dir);
        chmod($dir, 0555);

        $fileIo = $this->createMock(DirectoryWritabilityCheckerInterface::class);
        $fileIo->expects(self::exactly(2))
            ->method('isWritable')
            ->with($dir)
            ->willReturnOnConsecutiveCalls(false, true);

        $finder = new TempDirFinder($fileIo, $dir);

        self::assertSame($dir, $finder->getOrCreateTempDir());
        self::assertSame(0o700, $this->mode($dir));

        rmdir($dir);
    }

    #[RunInSeparateProcess]
    public function test_creates_a_missing_dir_for_its_owner_only(): void
    {
        $root = sys_get_temp_dir() . '/phel-new-' . uniqid('', true);
        $dir = $root . '/tmp';

        $finder = new TempDirFinder($this->writable(), $dir);

        self::assertSame($dir, $finder->getOrCreateTempDir());
        self::assertSame(0o700, $this->mode($dir));
        self::assertSame(0o700, $this->mode($root));

        rmdir($dir);
        rmdir($root);
    }

    #[RunInSeparateProcess]
    public function test_tightens_a_world_writable_dir_it_owns(): void
    {
        $dir = sys_get_temp_dir() . '/phel-open-' . uniqid('', true);
        mkdir($dir);
        chmod($dir, 0o777);

        new TempDirFinder($this->writable(), $dir)->getOrCreateTempDir();

        self::assertSame(0o700, $this->mode($dir));
        rmdir($dir);
    }

    #[RunInSeparateProcess]
    public function test_refuses_a_dir_inside_a_parent_others_can_write_to(): void
    {
        if (!function_exists('posix_geteuid')) {
            self::markTestSkipped('needs POSIX permissions');
        }

        $parent = sys_get_temp_dir() . '/phel-open-parent-' . uniqid('', true);
        mkdir($parent);
        chmod($parent, 0o777);

        try {
            $this->expectException(FileException::class);
            $this->expectExceptionMessage('Directory can be replaced by another user: ' . $parent);

            new TempDirFinder($this->writable(), $parent . '/tmp')->getOrCreateTempDir();
        } finally {
            @rmdir($parent . '/tmp');
            rmdir($parent);
        }
    }

    public function test_refuses_a_dir_another_user_owns(): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() === 0 || fileowner('/usr') === posix_geteuid()) {
            self::markTestSkipped('needs a directory owned by another user');
        }

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Directory is owned by another user: /usr');

        new TempDirFinder($this->writable(), '/usr')->getOrCreateTempDir();
    }

    private function writable(): DirectoryWritabilityCheckerInterface
    {
        $fileIo = self::createStub(DirectoryWritabilityCheckerInterface::class);
        $fileIo->method('isWritable')->willReturn(true);

        return $fileIo;
    }

    private function mode(string $path): int
    {
        clearstatcache();

        return fileperms($path) & 0o777;
    }
}
