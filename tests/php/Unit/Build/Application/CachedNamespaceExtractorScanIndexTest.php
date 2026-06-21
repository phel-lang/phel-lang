<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\CachedNamespaceExtractor;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Infrastructure\Cache\NullNamespaceCache;
use Phel\Build\Infrastructure\Cache\PhpScanIndexCache;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CachedNamespaceExtractorScanIndexTest extends TestCase
{
    private string $dir;

    private string $cacheFile;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phel-scan-index-test-' . uniqid();
        mkdir($this->dir, 0777, true);
        $this->cacheFile = $this->dir . '/.cache/scan-index.php';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function test_warm_run_serves_from_persisted_index_without_walking(): void
    {
        $this->writePhel('main.phel', '(ns app\\main)');

        $callCount = 0;
        // A fresh extractor + cache per "process", sharing only the on-disk file.
        $coldCache = new PhpScanIndexCache($this->cacheFile);
        $coldResult = $this->makeExtractor($coldCache, $callCount)->getNamespacesFromDirectories([$this->dir]);
        $coldCache->save();

        self::assertSame(1, $callCount, 'Cold run must walk and extract the single file.');
        self::assertCount(1, $coldResult);

        // Second process: same tree, fresh in-memory caches, persisted index present.
        $callCount = 0;
        $warmResult = $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)
            ->getNamespacesFromDirectories([$this->dir]);

        self::assertSame(0, $callCount, 'Warm run must not invoke the inner extractor (no walk).');
        self::assertSame('app\\main', $warmResult[0]->getNamespace());
    }

    public function test_added_file_invalidates_even_within_same_second(): void
    {
        $this->writePhel('main.phel', '(ns app\\main)');

        $callCount = 0;
        $coldCache = new PhpScanIndexCache($this->cacheFile);
        $this->makeExtractor($coldCache, $callCount)->getNamespacesFromDirectories([$this->dir]);
        $coldCache->save();

        // Add a second file but pin the directory mtime back to the cold value,
        // simulating an add within the same 1s mtime window. Only the file
        // count differs, which must still invalidate.
        $dirMtime = (int) filemtime($this->dir);
        $this->writePhel('extra.phel', '(ns app\\extra)');
        touch($this->dir, $dirMtime);
        clearstatcache();

        $callCount = 0;
        $result = $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)
            ->getNamespacesFromDirectories([$this->dir]);

        self::assertSame(2, $callCount, 'Added file (same-second) must force a re-walk via file-count mismatch.');
        self::assertCount(2, $result);
    }

    public function test_removed_file_invalidates_even_within_same_second(): void
    {
        $this->writePhel('main.phel', '(ns app\\main)');
        $this->writePhel('extra.phel', '(ns app\\extra)');

        $callCount = 0;
        $coldCache = new PhpScanIndexCache($this->cacheFile);
        $this->makeExtractor($coldCache, $callCount)->getNamespacesFromDirectories([$this->dir]);
        $coldCache->save();

        $dirMtime = (int) filemtime($this->dir);
        unlink($this->dir . '/extra.phel');
        touch($this->dir, $dirMtime);
        clearstatcache();

        $callCount = 0;
        $result = $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)
            ->getNamespacesFromDirectories([$this->dir]);

        self::assertSame(1, $callCount, 'Removed file (same-second) must force a re-walk via file-count mismatch.');
        self::assertCount(1, $result);
    }

    public function test_in_place_edit_invalidates_via_per_file_mtime(): void
    {
        $this->writePhel('main.phel', '(ns app\\main)');

        $callCount = 0;
        $coldCache = new PhpScanIndexCache($this->cacheFile);
        $this->makeExtractor($coldCache, $callCount)->getNamespacesFromDirectories([$this->dir]);
        $coldCache->save();

        // Edit in place: dir mtime + file count are unchanged, but the file's
        // own mtime advances. The per-file mtime check must invalidate so the
        // changed ns/deps are never served stale.
        $dirMtime = (int) filemtime($this->dir);
        file_put_contents($this->dir . '/main.phel', '(ns app\\main (:require app\\dep))');
        touch($this->dir . '/main.phel', time() + 5);
        touch($this->dir, $dirMtime);
        clearstatcache();

        $callCount = 0;
        $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)
            ->getNamespacesFromDirectories([$this->dir]);

        self::assertSame(1, $callCount, 'In-place edit must force a re-walk via per-file mtime mismatch.');
    }

    public function test_different_dir_sets_do_not_cross_contaminate(): void
    {
        $other = $this->dir . '/other';
        mkdir($other, 0777, true);
        $this->writePhel('main.phel', '(ns app\\main)');
        file_put_contents($other . '/lib.phel', '(ns app\\lib)');

        $callCount = 0;
        $cache = new PhpScanIndexCache($this->cacheFile);
        $extractor = $this->makeExtractor($cache, $callCount);

        $a = $extractor->getNamespacesFromDirectories([$this->dir]);
        $b = $extractor->getNamespacesFromDirectories([$other]);
        $cache->save();

        // The other dir lives under $this->dir, so scanning $this->dir also
        // surfaces lib.phel; the point is each dir-set key resolves to its own
        // persisted entry without serving the wrong set.
        $namespacesA = array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $a);

        self::assertContains('app\\main', $namespacesA);
        self::assertSame(
            ['app\\lib'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $b),
            'Scanning only the other dir must yield only its own namespace.',
        );

        // Warm run for the narrower dir-set must serve exactly that set.
        $callCount = 0;
        $warmB = $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)
            ->getNamespacesFromDirectories([$other]);

        self::assertSame(0, $callCount, 'Warm narrower dir-set must be served from its own persisted entry.');
        self::assertSame(
            ['app\\lib'],
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $warmB),
        );
    }

    private function writePhel(string $name, string $content): void
    {
        file_put_contents($this->dir . '/' . $name, $content);
    }

    /**
     * Build an extractor whose inner extractor derives `NamespaceInformation`
     * from the actual file content and counts how often it is invoked, so a
     * skipped walk is observable via a zero call count.
     */
    private function makeExtractor(PhpScanIndexCache $scanIndexCache, int &$callCount): CachedNamespaceExtractor
    {
        $inner = $this->createStub(NamespaceExtractorInterface::class);
        $inner->method('getNamespaceFromFile')->willReturnCallback(
            static function (string $path) use (&$callCount): NamespaceInformation {
                ++$callCount;
                $content = (string) file_get_contents($path);
                preg_match('/\(ns\s+([^\s\)]+)/', $content, $m);
                $deps = [];
                if (preg_match('/:require\s+([^\s\)]+)/', $content, $dm)) {
                    $deps[] = $dm[1];
                }

                return new NamespaceInformation($path, $m[1] ?? 'unknown', $deps, true);
            },
        );

        return new CachedNamespaceExtractor(
            $inner,
            new NullNamespaceCache(),
            new TopologicalNamespaceSorter(),
            null,
            $scanIndexCache,
        );
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
