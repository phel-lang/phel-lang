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
