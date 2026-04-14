<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Cache;

use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Integration tests for CompiledCodeCache verifying end-to-end cache behavior.
 */
final class CompiledCodeCacheIntegrationTest extends TestCase
{
    private string $cacheDir;

    private string $sourceFile;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-cache-integration-' . uniqid();
        $this->sourceFile = $this->cacheDir . '/source.phel';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_hit_loads_compiled_code(): void
    {
        $namespace = 'test\\integration\\cached';
        $sourceHash = md5('source content');
        $phpCode = '$testVar = "cached value";';

        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, $namespace, $sourceHash, $phpCode);

        $freshCache = new CompiledCodeCache($this->cacheDir);
        $cachedPath = $freshCache->get($this->sourceFile, $sourceHash);

        self::assertNotNull($cachedPath);
        self::assertFileExists($cachedPath);

        require $cachedPath;

        /** @var string $testVar */
        self::assertSame('cached value', $testVar);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_miss_returns_null(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        self::assertNull($cache->get($this->cacheDir . '/missing.phel', 'somehash'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_stale_cache_returns_null(): void
    {
        $namespace = 'test\\integration\\stale';
        $oldHash = md5('old source');
        $newHash = md5('new source');

        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, $namespace, $oldHash, '$x = 1;');

        self::assertNull($cache->get($this->sourceFile, $newHash));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_persists_across_instances(): void
    {
        $namespace = 'test\\integration\\persist';
        $sourceHash = md5('persistent source');
        $phpCode = '// persistent code';

        $cache1 = new CompiledCodeCache($this->cacheDir);
        $cache1->put($this->sourceFile, $namespace, $sourceHash, $phpCode);

        $cache2 = new CompiledCodeCache($this->cacheDir);
        $result = $cache2->get($this->sourceFile, $sourceHash);

        self::assertNotNull($result);
        self::assertFileExists($result);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_clear_removes_all(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->cacheDir . '/one.phel', 'ns\\one', 'hash1', '// code 1');
        $cache->put($this->cacheDir . '/two.phel', 'ns\\two', 'hash2', '// code 2');
        $cache->put($this->cacheDir . '/three.phel', 'ns\\three', 'hash3', '// code 3');

        $cache->clear();

        $freshCache = new CompiledCodeCache($this->cacheDir);
        self::assertNull($freshCache->get($this->cacheDir . '/one.phel', 'hash1'));
        self::assertNull($freshCache->get($this->cacheDir . '/two.phel', 'hash2'));
        self::assertNull($freshCache->get($this->cacheDir . '/three.phel', 'hash3'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_compiled_file_has_php_header(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, 'test\\header', 'hash', '$x = 42;');

        $compiledPath = $cache->getCompiledPath($this->sourceFile, 'test\\header');
        $content = file_get_contents($compiledPath);

        self::assertStringStartsWith('<?php', $content);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_multiple_files_in_one_namespace_stored_independently(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';

        $cache->put($fileA, 'shared\\ns', 'hash_a', '$result = "a";');
        $cache->put($fileB, 'shared\\ns', 'hash_b', '$result = "b";');

        self::assertNotNull($cache->get($fileA, 'hash_a'));
        self::assertNotNull($cache->get($fileB, 'hash_b'));

        $pathA = $cache->getCompiledPath($fileA, 'shared\\ns');
        $pathB = $cache->getCompiledPath($fileB, 'shared\\ns');
        self::assertNotSame($pathA, $pathB);
    }

    public function test_put_preserves_entries_written_by_other_instances(): void
    {
        // A (load ...) form creates a nested CompiledCodeCache instance that
        // writes entries for sub-files. The outer instance must not clobber
        // those entries when it saves its own, or the next run will see a
        // cache miss and recompile everything.
        $outerFile = $this->cacheDir . '/outer.phel';
        $nestedFile = $this->cacheDir . '/nested.phel';

        $outer = new CompiledCodeCache($this->cacheDir);
        $nested = new CompiledCodeCache($this->cacheDir);

        $nested->put($nestedFile, 'nested\\ns', 'hash_nested', '$result = "nested";');

        $outer->put($outerFile, 'outer\\ns', 'hash_outer', '$result = "outer";');

        $fresh = new CompiledCodeCache($this->cacheDir);
        self::assertNotNull($fresh->get($outerFile, 'hash_outer'));
        self::assertNotNull($fresh->get($nestedFile, 'hash_nested'));
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
