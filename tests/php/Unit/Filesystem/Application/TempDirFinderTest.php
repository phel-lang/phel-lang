<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application {
    function is_writable(string $filename): bool
    {
        return false;
    }
}

namespace PhelTest\Unit\Filesystem\Application {

    use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
    use Phel\Filesystem\Application\TempDirFinder;
    use PHPUnit\Framework\TestCase;

    final class TempDirFinderTest extends TestCase
    {
        #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
        public function test_throws_exception_when_dir_not_writable(): void
        {
            $dir = sys_get_temp_dir() . '/phel-unwritable-' . uniqid();
            mkdir($dir);

            $finder = new TempDirFinder($dir);

            $this->expectException(FileException::class);
            $this->expectExceptionMessage('Directory is not writable: ' . $dir);

            try {
                $finder->getOrCreateTempDir();
            } finally {
                rmdir($dir);
            }
        }
    }

}
