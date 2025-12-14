<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CompiledCodeCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    public function test_get_returns_null_for_unknown_namespace(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        $result = $cache->get('unknown\\namespace', 'abc123');

        self::assertNull($result);
    }

    public function test_get_returns_null_for_wrong_hash(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put('test\\namespace', 'original_hash', '// compiled code');

        $result = $cache->get('test\\namespace', 'different_hash');

        self::assertNull($result);
    }

    public function test_get_returns_path_for_valid_entry(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put('test\\namespace', 'source_hash', '// compiled code');

        $result = $cache->get('test\\namespace', 'source_hash');

        self::assertNotNull($result);
        self::assertFileExists($result);
    }

    public function test_put_stores_compiled_code(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $phpCode = '$x = 1 + 2;';

        $cache->put('test\\namespace', 'hash', $phpCode);

        $compiledPath = $cache->getCompiledPath('test\\namespace');
        self::assertFileExists($compiledPath);
        self::assertStringContainsString($phpCode, (string) file_get_contents($compiledPath));
    }

    public function test_put_adds_php_header(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $phpCode = '$x = 1;';

        $cache->put('test\\namespace', 'hash', $phpCode);

        $compiledPath = $cache->getCompiledPath('test\\namespace');
        $content = file_get_contents($compiledPath);
        self::assertStringStartsWith('<?php', $content);
    }

    public function test_invalidate_removes_entry(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put('test\\namespace', 'hash', '// code');

        $cache->invalidate('test\\namespace');

        $result = $cache->get('test\\namespace', 'hash');
        self::assertNull($result);
    }

    public function test_invalidate_deletes_file(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put('test\\namespace', 'hash', '// code');

        $compiledPath = $cache->getCompiledPath('test\\namespace');
        self::assertFileExists($compiledPath);

        $cache->invalidate('test\\namespace');

        self::assertFileDoesNotExist($compiledPath);
    }

    public function test_clear_removes_all_entries(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put('namespace\\one', 'hash1', '// code 1');
        $cache->put('namespace\\two', 'hash2', '// code 2');

        $cache->clear();

        self::assertNull($cache->get('namespace\\one', 'hash1'));
        self::assertNull($cache->get('namespace\\two', 'hash2'));
    }

    public function test_clear_deletes_all_files(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put('namespace\\one', 'hash1', '// code 1');
        $cache->put('namespace\\two', 'hash2', '// code 2');

        $path1 = $cache->getCompiledPath('namespace\\one');
        $path2 = $cache->getCompiledPath('namespace\\two');

        $cache->clear();

        self::assertFileDoesNotExist($path1);
        self::assertFileDoesNotExist($path2);
    }

    public function test_get_compiled_path_munges_namespace(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        $path = $cache->getCompiledPath('my\\test\\namespace');

        self::assertStringContainsString('my_test_namespace.php', $path);
    }

    public function test_get_returns_null_when_file_deleted_externally(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put('test\\namespace', 'hash', '// code');

        $compiledPath = $cache->getCompiledPath('test\\namespace');
        unlink($compiledPath);

        $result = $cache->get('test\\namespace', 'hash');

        self::assertNull($result);
    }

    public function test_cache_persists_across_instances(): void
    {
        $cache1 = new CompiledCodeCache($this->cacheDir);
        $cache1->put('test\\namespace', 'hash', '// code');

        $cache2 = new CompiledCodeCache($this->cacheDir);
        $result = $cache2->get('test\\namespace', 'hash');

        self::assertNotNull($result);
    }

    public function test_cache_persists_across_instances_with_same_version(): void
    {
        $cache1 = new CompiledCodeCache($this->cacheDir, 'v1.0.0');
        $cache1->put('test\\namespace', 'hash', '// code');

        $cache2 = new CompiledCodeCache($this->cacheDir, 'v1.0.0');
        $result = $cache2->get('test\\namespace', 'hash');

        self::assertNotNull($result);
    }

    public function test_cache_invalidates_when_phel_version_changes(): void
    {
        $cache1 = new CompiledCodeCache($this->cacheDir, 'v1.0.0');
        $cache1->put('test\\namespace', 'hash', '// code');

        // New instance with different Phel version should not find the cached entry
        $cache2 = new CompiledCodeCache($this->cacheDir, 'v2.0.0');
        $result = $cache2->get('test\\namespace', 'hash');

        self::assertNull($result);
    }

    public function test_lru_eviction_removes_oldest_entries(): void
    {
        // Create cache with max 5 entries
        $cache = new CompiledCodeCache($this->cacheDir, '', 5);

        // Add 5 entries
        for ($i = 1; $i <= 5; ++$i) {
            $cache->put('namespace\ns' . $i, 'hash' . $i, '// code ' . $i);
        }

        // Access ns3 to make it recently used
        $cache->get('namespace\\ns3', 'hash3');

        // Add a 6th entry - should trigger eviction of oldest (ns1)
        $cache->put('namespace\\ns6', 'hash6', '// code 6');

        // ns1 should be evicted (oldest, never accessed after put)
        self::assertNull($cache->get('namespace\\ns1', 'hash1'));

        // ns3 should still exist (was accessed, so more recent)
        self::assertNotNull($cache->get('namespace\\ns3', 'hash3'));

        // ns6 should exist (just added)
        self::assertNotNull($cache->get('namespace\\ns6', 'hash6'));
    }

    public function test_lru_eviction_deletes_files(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir, '', 3);

        // Add 3 entries
        $cache->put('namespace\\ns1', 'hash1', '// code 1');
        $cache->put('namespace\\ns2', 'hash2', '// code 2');
        $cache->put('namespace\\ns3', 'hash3', '// code 3');

        $path1 = $cache->getCompiledPath('namespace\\ns1');
        self::assertFileExists($path1);

        // Add 4th entry - should trigger eviction
        $cache->put('namespace\\ns4', 'hash4', '// code 4');

        // File for evicted entry should be deleted
        self::assertFileDoesNotExist($path1);
    }

    public function test_no_eviction_when_under_max_entries(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir, '', 10);

        // Add 5 entries (under max of 10)
        for ($i = 1; $i <= 5; ++$i) {
            $cache->put('namespace\ns' . $i, 'hash' . $i, '// code ' . $i);
        }

        // All entries should still exist
        for ($i = 1; $i <= 5; ++$i) {
            self::assertNotNull($cache->get('namespace\ns' . $i, 'hash' . $i));
        }
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
