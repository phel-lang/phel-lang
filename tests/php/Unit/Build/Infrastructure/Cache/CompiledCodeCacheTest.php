<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Cache;

use Phel\Build\Infrastructure\Cache\BundledCompiledCache;
use Phel\Build\Infrastructure\Cache\CompiledCodeCache;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function sprintf;

final class CompiledCodeCacheTest extends TestCase
{
    private string $cacheDir;

    private string $sourceFile;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-test-' . uniqid('', true);
        $this->sourceFile = $this->cacheDir . '/test.phel';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    public function test_get_returns_null_for_unknown_source(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        self::assertNull($cache->get('/unknown/file.phel', 'abc123'));
    }

    public function test_get_returns_null_for_wrong_hash(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, 'test\\namespace', 'original_hash', '// compiled code');

        self::assertNull($cache->get($this->sourceFile, 'different_hash'));
    }

    public function test_get_returns_path_for_valid_entry(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, 'test\\namespace', 'source_hash', '// compiled code');

        $result = $cache->get($this->sourceFile, 'source_hash');

        self::assertNotNull($result);
        self::assertFileExists($result);
    }

    public function test_put_stores_compiled_code(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $phpCode = '$x = 1 + 2;';

        $cache->put($this->sourceFile, 'test\\namespace', 'hash', $phpCode);

        $compiledPath = $cache->getCompiledPath($this->sourceFile, 'test\\namespace');
        self::assertFileExists($compiledPath);
        self::assertStringContainsString($phpCode, (string) file_get_contents($compiledPath));
    }

    public function test_put_adds_php_header(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        $cache->put($this->sourceFile, 'test\\namespace', 'hash', '$x = 1;');

        $compiledPath = $cache->getCompiledPath($this->sourceFile, 'test\\namespace');
        self::assertStringStartsWith('<?php', (string) file_get_contents($compiledPath));
    }

    public function test_invalidate_removes_entry(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, 'test\\namespace', 'hash', '// code');

        $cache->invalidate($this->sourceFile);

        self::assertNull($cache->get($this->sourceFile, 'hash'));
    }

    public function test_invalidate_deletes_file(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, 'test\\namespace', 'hash', '// code');

        $compiledPath = $cache->getCompiledPath($this->sourceFile, 'test\\namespace');
        self::assertFileExists($compiledPath);

        $cache->invalidate($this->sourceFile);

        self::assertFileDoesNotExist($compiledPath);
    }

    public function test_invalidate_only_removes_targeted_file(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        $cache->put($fileA, 'shared\\ns', 'hashA', '// A');
        $cache->put($fileB, 'shared\\ns', 'hashB', '// B');

        $cache->invalidate($fileA);

        self::assertNull($cache->get($fileA, 'hashA'));
        self::assertNotNull($cache->get($fileB, 'hashB'));
    }

    public function test_invalidate_then_put_other_file_does_not_resurrect_invalidated_entry(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        $cache->put($fileA, 'shared\\ns', 'hashA', '// A');
        $cache->put($fileB, 'shared\\ns', 'hashB', '// B');

        $cache->invalidate($fileA);
        $cache->put($fileB, 'shared\\ns', 'hashB2', '// B2');

        // After invalidating A and putting B again, A must stay invalidated;
        // earlier the disk merge in saveEntries() would resurrect A's entry.
        self::assertFalse($cache->has($fileA));
        self::assertNull($cache->get($fileA, 'hashA'));
    }

    public function test_invalidated_entry_does_not_resurrect_across_new_instance_after_put(): void
    {
        $cache1 = new CompiledCodeCache($this->cacheDir);
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        $cache1->put($fileA, 'shared\\ns', 'hashA', '// A');
        $cache1->put($fileB, 'shared\\ns', 'hashB', '// B');
        $cache1->invalidate($fileA);
        $cache1->put($fileB, 'shared\\ns', 'hashB2', '// B2');

        $cache2 = new CompiledCodeCache($this->cacheDir);

        self::assertFalse($cache2->has($fileA));
        self::assertNotNull($cache2->get($fileB, 'hashB2'));
    }

    public function test_clear_removes_all_entries(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        $cache->put($fileA, 'namespace\\one', 'hash1', '// code 1');
        $cache->put($fileB, 'namespace\\two', 'hash2', '// code 2');

        $cache->clear();

        self::assertNull($cache->get($fileA, 'hash1'));
        self::assertNull($cache->get($fileB, 'hash2'));
    }

    public function test_clear_deletes_all_files(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        $cache->put($fileA, 'namespace\\one', 'hash1', '// code 1');
        $cache->put($fileB, 'namespace\\two', 'hash2', '// code 2');

        $path1 = $cache->getCompiledPath($fileA, 'namespace\\one');
        $path2 = $cache->getCompiledPath($fileB, 'namespace\\two');

        $cache->clear();

        self::assertFileDoesNotExist($path1);
        self::assertFileDoesNotExist($path2);
    }

    public function test_get_compiled_path_munges_namespace_and_includes_source_fingerprint(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        $path = $cache->getCompiledPath($this->sourceFile, 'my\\test\\namespace');

        self::assertStringContainsString('my_test_namespace__', $path);
        self::assertStringEndsWith('.php', $path);
    }

    public function test_two_files_sharing_a_namespace_get_distinct_cache_files(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';

        $pathA = $cache->getCompiledPath($fileA, 'shared\\ns');
        $pathB = $cache->getCompiledPath($fileB, 'shared\\ns');

        self::assertNotSame($pathA, $pathB);
    }

    public function test_two_files_sharing_a_namespace_do_not_clobber_each_other(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $fileA = $this->cacheDir . '/a.phel';
        $fileB = $this->cacheDir . '/b.phel';
        $cache->put($fileA, 'shared\\ns', 'hashA', '$a = 1;');
        $cache->put($fileB, 'shared\\ns', 'hashB', '$b = 2;');

        $pathA = $cache->get($fileA, 'hashA');
        $pathB = $cache->get($fileB, 'hashB');

        self::assertNotNull($pathA);
        self::assertNotNull($pathB);
        self::assertStringContainsString('$a = 1;', (string) file_get_contents($pathA));
        self::assertStringContainsString('$b = 2;', (string) file_get_contents($pathB));
    }

    public function test_get_returns_null_when_file_deleted_externally(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $cache->put($this->sourceFile, 'test\\namespace', 'hash', '// code');

        $compiledPath = $cache->getCompiledPath($this->sourceFile, 'test\\namespace');
        unlink($compiledPath);

        self::assertNull($cache->get($this->sourceFile, 'hash'));
    }

    public function test_cache_persists_across_instances(): void
    {
        $cache1 = new CompiledCodeCache($this->cacheDir);
        $cache1->put($this->sourceFile, 'test\\namespace', 'hash', '// code');

        $cache2 = new CompiledCodeCache($this->cacheDir);

        self::assertNotNull($cache2->get($this->sourceFile, 'hash'));
    }

    public function test_cache_persists_across_instances_with_same_version(): void
    {
        $cache1 = new CompiledCodeCache($this->cacheDir, 'v1.0.0');
        $cache1->put($this->sourceFile, 'test\\namespace', 'hash', '// code');

        $cache2 = new CompiledCodeCache($this->cacheDir, 'v1.0.0');

        self::assertNotNull($cache2->get($this->sourceFile, 'hash'));
    }

    public function test_cache_invalidates_when_phel_version_changes(): void
    {
        $cache1 = new CompiledCodeCache($this->cacheDir, 'v1.0.0');
        $cache1->put($this->sourceFile, 'test\\namespace', 'hash', '// code');

        $cache2 = new CompiledCodeCache($this->cacheDir, 'v2.0.0');

        self::assertNull($cache2->get($this->sourceFile, 'hash'));
    }

    public function test_lru_eviction_removes_oldest_entries(): void
    {
        // Seed entries in one process, then let a fresh instance evict.
        // Entries are only evictable once a *later* process inherits them
        // from disk without touching them; the producing process protects
        // its own working set (see test_entries_touched_this_process_are_not_evicted).
        $seed = new CompiledCodeCache($this->cacheDir, '', 5);
        for ($i = 1; $i <= 5; ++$i) {
            $seed->put($this->cacheDir . sprintf('/ns%d.phel', $i), 'ns' . $i, 'hash' . $i, '// code ' . $i);
        }

        $cache = new CompiledCodeCache($this->cacheDir, '', 5);

        // Access ns3 to mark it recently used in this process
        $cache->get($this->cacheDir . '/ns3.phel', 'hash3');

        // Add a 6th entry — should evict the oldest untouched entry (ns1)
        $cache->put($this->cacheDir . '/ns6.phel', 'ns6', 'hash6', '// code 6');

        self::assertNull($cache->get($this->cacheDir . '/ns1.phel', 'hash1'));
        self::assertNotNull($cache->get($this->cacheDir . '/ns3.phel', 'hash3'));
        self::assertNotNull($cache->get($this->cacheDir . '/ns6.phel', 'hash6'));
    }

    public function test_lru_eviction_deletes_files(): void
    {
        $seed = new CompiledCodeCache($this->cacheDir, '', 3);
        $seed->put($this->cacheDir . '/ns1.phel', 'ns1', 'hash1', '// code 1');
        $seed->put($this->cacheDir . '/ns2.phel', 'ns2', 'hash2', '// code 2');
        $seed->put($this->cacheDir . '/ns3.phel', 'ns3', 'hash3', '// code 3');

        $path1 = $seed->getCompiledPath($this->cacheDir . '/ns1.phel', 'ns1');
        self::assertFileExists($path1);

        $cache = new CompiledCodeCache($this->cacheDir, '', 3);
        $cache->put($this->cacheDir . '/ns4.phel', 'ns4', 'hash4', '// code 4');

        self::assertFileDoesNotExist($path1);
    }

    public function test_entries_touched_this_process_are_not_evicted(): void
    {
        // A build `(load ...)`s its secondaries into the cache and the
        // SecondaryFileHarvester reads them back at the end of the same run.
        // Even when the run exceeds maxEntries, none of its own entries may
        // be dropped, or the build would ship a `(load ...)` with no sibling.
        $cache = new CompiledCodeCache($this->cacheDir, '', 3);

        for ($i = 1; $i <= 6; ++$i) {
            $cache->put($this->cacheDir . sprintf('/ns%d.phel', $i), 'ns' . $i, 'hash' . $i, '// code ' . $i);
        }

        for ($i = 1; $i <= 6; ++$i) {
            self::assertNotNull(
                $cache->get($this->cacheDir . sprintf('/ns%d.phel', $i), 'hash' . $i),
                sprintf('Entry ns%d produced this process must survive eviction', $i),
            );
        }
    }

    public function test_no_eviction_when_under_max_entries(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir, '', 10);

        for ($i = 1; $i <= 5; ++$i) {
            $cache->put($this->cacheDir . sprintf('/ns%d.phel', $i), 'ns' . $i, 'hash' . $i, '// code ' . $i);
        }

        for ($i = 1; $i <= 5; ++$i) {
            self::assertNotNull($cache->get($this->cacheDir . sprintf('/ns%d.phel', $i), 'hash' . $i));
        }
    }

    public function test_put_environment_and_get_environment_round_trip(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $envData = [
            'refers' => ['map' => ['ns' => null, 'name' => 'phel.core']],
            'require_aliases' => ['str' => ['ns' => null, 'name' => 'phel\\string']],
            'use_aliases' => ['Exception' => ['ns' => null, 'name' => 'Exception']],
        ];

        $cache->putEnvironment('test\\namespace', $envData);

        self::assertSame($envData, $cache->getEnvironment('test\\namespace'));
    }

    public function test_get_environment_returns_null_when_no_env_file(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        self::assertNull($cache->getEnvironment('nonexistent\\namespace'));
    }

    public function test_get_environment_path_munges_namespace(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        $path = $cache->getEnvironmentPath('my\\test\\namespace');

        self::assertStringContainsString('my_test_namespace.env.php', $path);
    }

    public function test_put_environment_persists_across_instances(): void
    {
        $envData = [
            'refers' => ['x' => ['ns' => null, 'name' => 'foo']],
            'require_aliases' => [],
            'use_aliases' => [],
        ];

        $cache1 = new CompiledCodeCache($this->cacheDir);
        $cache1->putEnvironment('test\\namespace', $envData);

        $cache2 = new CompiledCodeCache($this->cacheDir);

        self::assertSame($envData, $cache2->getEnvironment('test\\namespace'));
    }

    public function test_put_environment_with_empty_data(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);
        $envData = [
            'refers' => [],
            'require_aliases' => [],
            'use_aliases' => [],
        ];

        $cache->putEnvironment('test\\namespace', $envData);

        self::assertSame($envData, $cache->getEnvironment('test\\namespace'));
    }

    public function test_get_falls_back_to_bundled_when_writable_cache_misses(): void
    {
        $bundledDir = $this->cacheDir . '/bundled';
        mkdir($bundledDir, 0755, true);
        $bundled = new BundledCompiledCache($bundledDir);
        file_put_contents($bundled->compiledTarget('bundled_hash'), "<?php\n// bundled");

        $cache = new CompiledCodeCache(
            $this->cacheDir,
            bundled: $bundled,
        );

        $result = $cache->get('/any/uncached.phel', 'bundled_hash');

        self::assertNotNull($result);
        self::assertStringContainsString('// bundled', (string) file_get_contents($result));
    }

    public function test_get_prefers_writable_cache_over_bundled(): void
    {
        $bundledDir = $this->cacheDir . '/bundled';
        mkdir($bundledDir, 0755, true);
        $bundled = new BundledCompiledCache($bundledDir);
        file_put_contents($bundled->compiledTarget('hash'), "<?php\n// bundled");

        $cache = new CompiledCodeCache($this->cacheDir, bundled: $bundled);
        $cache->put($this->sourceFile, 'test\\namespace', 'hash', '// writable');

        $result = $cache->get($this->sourceFile, 'hash');

        self::assertNotNull($result);
        self::assertStringContainsString('// writable', (string) file_get_contents($result));
    }

    public function test_get_environment_falls_back_to_bundled(): void
    {
        $bundledDir = $this->cacheDir . '/bundled';
        mkdir($bundledDir, 0755, true);
        $bundled = new BundledCompiledCache($bundledDir);
        $envData = ['refers' => [], 'require_aliases' => [], 'use_aliases' => []];
        file_put_contents(
            $bundled->environmentTarget('phel\\core'),
            '<?php return ' . var_export($envData, true) . ';',
        );

        $cache = new CompiledCodeCache($this->cacheDir, bundled: $bundled);

        self::assertSame($envData, $cache->getEnvironment('phel\\core'));
    }

    public function test_get_without_bundled_returns_null_on_miss(): void
    {
        $cache = new CompiledCodeCache($this->cacheDir);

        self::assertNull($cache->get('/any/uncached.phel', 'whatever'));
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
