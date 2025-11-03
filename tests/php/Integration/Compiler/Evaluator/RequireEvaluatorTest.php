<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Evaluator;

use Phel\Compiler\Domain\Evaluator\RequireEvaluator;
use Phel\Compiler\Infrastructure\CompiledCodeCache;
use Phel\Filesystem\FilesystemFacade;
use Phel\Filesystem\Infrastructure\RealFilesystem;
use Phel\Phel;
use PHPUnit\Framework\TestCase;

final class RequireEvaluatorTest extends TestCase
{
    private RequireEvaluator $evaluator;

    private FilesystemFacade $filesystem;

    private CompiledCodeCache $compiledCodeCache;

    private string $tempDir = '';

    protected function setUp(): void
    {
        RealFilesystem::reset();
        $this->filesystem = new FilesystemFacade();
        $this->compiledCodeCache = new CompiledCodeCache($this->filesystem);
        $this->evaluator = new RequireEvaluator($this->compiledCodeCache);
    }

    protected function tearDown(): void
    {
        $this->filesystem->clearAll();
        $this->compiledCodeCache->clear();

        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            $this->deleteDirRecursive($this->tempDir);
        }
    }

    public function test_it_evaluates_code_with_caching(): void
    {
        Phel::bootstrap(__DIR__);
        // Clean up any existing cache
        $tempDir = $this->filesystem->getTempDir();
        $cacheDir = $tempDir . '/' . CompiledCodeCache::CACHE_SUBDIR;

        if (is_dir($cacheDir)) {
            $this->compiledCodeCache->clear();
        }

        $result = $this->evaluator->eval('return 42;');

        self::assertSame(42, $result);
        self::assertDirectoryExists($cacheDir);
    }

    private function deleteDirRecursive(string $dir): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirRecursive($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
