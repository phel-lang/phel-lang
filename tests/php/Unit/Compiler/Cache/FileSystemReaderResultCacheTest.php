<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Cache;

use Phel\Compiler\Domain\Cache\CachedReaderResult;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Infrastructure\Cache\FileSystemReaderResultCache;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use PHPUnit\Framework\TestCase;

final class FileSystemReaderResultCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/phel-rr-cache-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->cacheDir);
    }

    public function test_load_misses_when_nothing_saved(): void
    {
        $cache = new FileSystemReaderResultCache($this->cacheDir, 'v1');

        self::assertNull($cache->load('(+ 1 2)', 0));
    }

    public function test_save_then_load_round_trips_forms_and_gensym_deltas(): void
    {
        $cache = new FileSystemReaderResultCache($this->cacheDir, 'v1');
        $entries = [$this->entry('alpha', 3), $this->entry('beta', 0)];

        $cache->save('(source)', 0, $entries);
        $loaded = $cache->load('(source)', 0);

        self::assertNotNull($loaded);
        self::assertCount(2, $loaded);
        self::assertSame('alpha', $loaded[0]->readerResult->getAst());
        self::assertSame('beta', $loaded[1]->readerResult->getAst());
        self::assertSame('alpha', $loaded[0]->readerResult->getCodeSnippet()->getCode());
        self::assertSame(3, $loaded[0]->gensymDelta);
        self::assertSame(0, $loaded[1]->gensymDelta);
    }

    public function test_save_creates_the_read_result_subdir(): void
    {
        $cache = new FileSystemReaderResultCache($this->cacheDir, 'v1');

        $cache->save('(x)', 0, [$this->entry('x')]);

        self::assertDirectoryExists($this->cacheDir . '/read-result');
    }

    public function test_different_optimization_level_is_a_miss(): void
    {
        $cache = new FileSystemReaderResultCache($this->cacheDir, 'v1');
        $cache->save('(same)', 0, [$this->entry('x')]);

        self::assertNull($cache->load('(same)', 2));
    }

    public function test_different_source_is_a_miss(): void
    {
        $cache = new FileSystemReaderResultCache($this->cacheDir, 'v1');
        $cache->save('(one)', 0, [$this->entry('x')]);

        self::assertNull($cache->load('(two)', 0));
    }

    public function test_different_phel_version_busts_the_entry(): void
    {
        $old = new FileSystemReaderResultCache($this->cacheDir, 'v1');
        $old->save('(same)', 0, [$this->entry('x')]);

        $new = new FileSystemReaderResultCache($this->cacheDir, 'v2');

        self::assertNull($new->load('(same)', 0));
    }

    public function test_corrupt_cache_file_loads_as_a_miss(): void
    {
        $cache = new FileSystemReaderResultCache($this->cacheDir, 'v1');
        $cache->save('(x)', 0, [$this->entry('x')]);

        foreach (glob($this->cacheDir . '/read-result/*.cache') ?: [] as $file) {
            file_put_contents($file, 'not-a-valid-gzip-blob');
        }

        self::assertNull($cache->load('(x)', 0));
    }

    private function entry(string $ast, int $gensymDelta = 0): CachedReaderResult
    {
        return new CachedReaderResult(
            new ReaderResult(
                $ast,
                new CodeSnippet(
                    new SourceLocation('test', 1, 1),
                    new SourceLocation('test', 1, 5),
                    $ast,
                ),
            ),
            $gensymDelta,
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*') ?: [];
        foreach ($files as $file) {
            is_dir($file) ? $this->removeDir($file) : @unlink($file);
        }

        @rmdir($dir);
    }
}
