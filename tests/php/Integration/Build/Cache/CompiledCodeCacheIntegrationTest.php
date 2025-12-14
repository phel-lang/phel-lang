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

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-cache-integration-' . uniqid();
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
        $cache->put($namespace, $sourceHash, $phpCode);

        // Create new instance to simulate fresh process
        $freshCache = new CompiledCodeCache($this->cacheDir);
        $cachedPath = $freshCache->get($namespace, $sourceHash);

        self::assertNotNull($cachedPath);
        self::assertFileExists($cachedPath);

        // Verify the code can be included
        require $cachedPath;

        /** @var string $testVar */
        self::assertSame('cached value', $testVar);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_miss_returns_null(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        $result = $cache->get('nonexistent\\namespace', 'somehash');

        self::assertNull($result);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_stale_cache_returns_null(): void
    {
        $namespace = 'test\\integration\\stale';
        $oldHash = md5('old source');
        $newHash = md5('new source');
        $phpCode = '$x = 1;';

        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($namespace, $oldHash, $phpCode);

        // Check with different hash (simulating source change)
        $result = $cache->get($namespace, $newHash);

        self::assertNull($result);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_persists_across_instances(): void
    {
        $namespace = 'test\\integration\\persist';
        $sourceHash = md5('persistent source');
        $phpCode = '// persistent code';

        // Store in first instance
        $cache1 = new CompiledCodeCache($this->cacheDir);
        $cache1->put($namespace, $sourceHash, $phpCode);

        // Retrieve from second instance
        $cache2 = new CompiledCodeCache($this->cacheDir);
        $result = $cache2->get($namespace, $sourceHash);

        self::assertNotNull($result);
        self::assertFileExists($result);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_clear_removes_all(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put('ns\\one', 'hash1', '// code 1');
        $cache->put('ns\\two', 'hash2', '// code 2');
        $cache->put('ns\\three', 'hash3', '// code 3');

        $cache->clear();

        // Verify all entries are gone
        $freshCache = new CompiledCodeCache($this->cacheDir);
        self::assertNull($freshCache->get('ns\\one', 'hash1'));
        self::assertNull($freshCache->get('ns\\two', 'hash2'));
        self::assertNull($freshCache->get('ns\\three', 'hash3'));
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_compiled_file_has_php_header(): void
    {
        $namespace = 'test\\header';
        $sourceHash = 'hash';
        $phpCode = '$x = 42;';

        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($namespace, $sourceHash, $phpCode);

        $compiledPath = $cache->getCompiledPath($namespace);
        $content = file_get_contents($compiledPath);

        self::assertStringStartsWith('<?php', $content);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_multiple_namespaces_stored_independently(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        $cache->put('namespace\\a', 'hash_a', '$result = "a";');
        $cache->put('namespace\\b', 'hash_b', '$result = "b";');

        self::assertNotNull($cache->get('namespace\\a', 'hash_a'));
        self::assertNotNull($cache->get('namespace\\b', 'hash_b'));

        // Verify they're different files
        $pathA = $cache->getCompiledPath('namespace\\a');
        $pathB = $cache->getCompiledPath('namespace\\b');
        self::assertNotSame($pathA, $pathB);
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
