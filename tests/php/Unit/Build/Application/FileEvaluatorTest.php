<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\FileEvaluator;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Shared\Facade\CompilerFacadeInterface;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileEvaluatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phel-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_eval_file_uses_cache_on_hit(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        file_put_contents($sourceFile, '(ns test\\namespace)');
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';
        $sourceHash = md5('(ns test\\namespace)');

        // Set up cache with valid entry
        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($namespace, $sourceHash, '$result = 42;');

        // Compiler should NOT be called when cache hits
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->never())->method('compileForCache');
        $compilerFacade->expects($this->never())->method('eval');

        $namespaceExtractor = $this->createMock(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel\\core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($sourceFile, $result->getSourceFile());
        self::assertSame($namespace, $result->getNamespace());
        self::assertStringContainsString('test_namespace.php', $result->getTargetFile());
    }

    public function test_eval_file_compiles_on_cache_miss(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        file_put_contents($sourceFile, '(ns test\\namespace)');
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';

        $cache = new CompiledCodeCache($cacheDir);

        // Compiler should be called when cache misses
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())
            ->method('compileForCache')
            ->willReturn(new EmitterResult(false, '$compiled = true;', '', ''));

        $namespaceExtractor = $this->createMock(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel\\core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($sourceFile, $result->getSourceFile());
        self::assertSame($namespace, $result->getNamespace());
    }

    public function test_eval_file_stores_result_in_cache_after_compilation(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $sourceCode = '(ns test\\namespace)';
        file_put_contents($sourceFile, $sourceCode);
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';
        $sourceHash = md5($sourceCode);
        $compiledCode = '$result = 123;';

        $cache = new CompiledCodeCache($cacheDir);

        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->method('compileForCache')
            ->willReturn(new EmitterResult(false, $compiledCode, '', ''));

        $namespaceExtractor = $this->createMock(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel\\core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $evaluator->evalFile($sourceFile);

        // Verify cache now has the entry
        $cachedPath = $cache->get($namespace, $sourceHash);
        self::assertNotNull($cachedPath);
        self::assertFileExists($cachedPath);
        self::assertStringContainsString($compiledCode, (string) file_get_contents($cachedPath));
    }

    public function test_eval_file_without_cache_compiles_directly(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        file_put_contents($sourceFile, '(ns test\\namespace)');
        $namespace = 'test\\namespace';

        // No cache provided
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())->method('eval');
        $compilerFacade->expects($this->never())->method('compileForCache');

        $namespaceExtractor = $this->createMock(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel\\core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($sourceFile, $result->getSourceFile());
        self::assertSame($namespace, $result->getNamespace());
        self::assertSame('', $result->getTargetFile());
    }

    public function test_eval_file_recompiles_when_source_hash_changes(): void
    {
        $sourceFile = $this->tempDir . '/test.phel';
        $cacheDir = $this->tempDir . '/cache';
        $namespace = 'test\\namespace';
        $oldCode = '(ns test\\namespace)';
        $newCode = '(ns test\\namespace) (def x 1)';
        $oldHash = md5($oldCode);

        // Put old version in cache
        file_put_contents($sourceFile, $oldCode);
        $cache = new CompiledCodeCache($cacheDir);
        $cache->put($namespace, $oldHash, '$old = true;');

        // Update source file
        file_put_contents($sourceFile, $newCode);

        // Compiler should be called because hash changed
        $compilerFacade = $this->createMock(CompilerFacadeInterface::class);
        $compilerFacade->expects($this->once())
            ->method('compileForCache')
            ->willReturn(new EmitterResult(false, '$new = true;', '', ''));

        $namespaceExtractor = $this->createMock(NamespaceExtractorInterface::class);
        $namespaceExtractor->method('getNamespaceFromFile')->willReturn(
            new NamespaceInformation($sourceFile, $namespace, ['phel\\core']),
        );

        $evaluator = new FileEvaluator($compilerFacade, $namespaceExtractor, $cache);
        $result = $evaluator->evalFile($sourceFile);

        self::assertSame($sourceFile, $result->getSourceFile());
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
