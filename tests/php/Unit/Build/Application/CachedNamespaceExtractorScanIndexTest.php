<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel;
use Phel\Build\Application\CachedNamespaceExtractor;
use Phel\Build\Application\NamespaceExtractor;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Infrastructure\Cache\NullNamespaceCache;
use Phel\Build\Infrastructure\Cache\PhpScanIndexCache;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Compiler\CompilerFacade;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\NamespaceInformation;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\TestCase;

final class CachedNamespaceExtractorScanIndexTest extends TestCase
{
    use RemoveDirTrait;

    private string $dir;

    private string $cacheFile;

    protected function setUp(): void
    {
        // realpath: on macOS the temp dir is a symlink, and a path that does
        // not exist yet cannot be resolved, so a scan key would otherwise
        // differ between "before the directory existed" and "after".
        $this->dir = realpath(sys_get_temp_dir()) . '/phel-scan-index-test-' . uniqid();
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

    /**
     * The rewrite keeps the file's mtime, the directory's mtime and the file
     * count, so only the time the scan started can tell it apart (#3537). The
     * future mtime stands in for "the same second as the scan", without racing
     * the clock.
     */
    public function test_same_second_rewrite_is_not_served_from_the_scan_index(): void
    {
        $file = $this->dir . '/main.phel';
        $sameSecond = time() + 60;
        file_put_contents($file, '(ns app\\main (:require app\\does-not-exist))');
        touch($file, $sameSecond);

        $callCount = 0;
        $coldCache = new PhpScanIndexCache($this->cacheFile);
        $this->makeExtractor($coldCache, $callCount)->getNamespacesFromDirectories([$this->dir]);
        $coldCache->save();

        $dirMtime = (int) filemtime($this->dir);
        file_put_contents($file, '(ns app\\main)');
        touch($file, $sameSecond);
        touch($this->dir, $dirMtime);
        clearstatcache();

        $callCount = 0;
        $result = $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)
            ->getNamespacesFromDirectories([$this->dir]);

        self::assertSame(1, $callCount, 'A racily clean scan must be walked again, not served.');
        self::assertSame([], $result[0]->getDependencies());
    }

    public function test_different_dir_sets_do_not_cross_contaminate(): void
    {
        $other = $this->dir . '/other';
        mkdir($other, 0777, true);
        $this->writePhel('main.phel', '(ns app\\main)');
        $this->writePhel('other/lib.phel', '(ns app\\lib)');

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

    public function test_a_configured_directory_created_after_the_scan_invalidates_the_index(): void
    {
        // Sibling roots, so a change under one is invisible to the other's
        // fingerprint: exactly the layout of `src-dirs` next to `test-dirs`.
        $src = $this->dir . '/src';
        $tests = $this->dir . '/tests';
        mkdir($src, 0777, true);
        $this->writePhel('src/main.phel', '(ns app\\main)');

        $callCount = 0;
        $coldCache = new PhpScanIndexCache($this->cacheFile);
        $cold = $this->makeExtractor($coldCache, $callCount)->getNamespacesFromDirectories([$src, $tests]);
        $coldCache->save();
        self::assertSame(['app\\main'], array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $cold));

        // The test directory did not exist at scan time; it does now, with a file.
        mkdir($tests, 0777, true);
        $this->writePhel('tests/main_test.phel', '(ns app\\main-test)');

        $callCount = 0;
        $warm = $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)
            ->getNamespacesFromDirectories([$src, $tests]);

        self::assertGreaterThan(0, $callCount, 'A directory that appeared after the scan must force a re-walk.');
        self::assertContains(
            'app\\main-test',
            array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $warm),
        );
    }

    public function test_a_still_missing_directory_keeps_the_index_valid(): void
    {
        $src = $this->dir . '/src';
        $missing = $this->dir . '/never-created';
        mkdir($src, 0777, true);
        $this->writePhel('src/main.phel', '(ns app\\main)');

        $callCount = 0;
        $coldCache = new PhpScanIndexCache($this->cacheFile);
        $this->makeExtractor($coldCache, $callCount)->getNamespacesFromDirectories([$src, $missing]);
        $coldCache->save();

        $callCount = 0;
        $warm = $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)
            ->getNamespacesFromDirectories([$src, $missing]);

        self::assertSame(0, $callCount, 'A directory that is still absent changes nothing; the index must be served.');
        self::assertSame(['app\\main'], array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $warm));
    }

    /**
     * Build an extractor whose inner extractor derives `NamespaceInformation`
     * from the actual file content and counts how often it is invoked, so a
     * skipped walk is observable via a zero call count.
     */
    /**
     * A lenient scan skips the bad file; a stored index would then let a later
     * build serve the same scan and never see the file it must fail on.
     */
    public function test_a_scan_that_skipped_a_bad_ns_form_is_not_persisted(): void
    {
        Phel::bootstrap(__DIR__);
        $this->writePhel('main.phel', '(ns app.main)');
        $this->writePhel('bad.phel', '(ns app.bad (:require [phel.string :refer :all]))');

        $cache = new PhpScanIndexCache($this->cacheFile);
        $extractor = new CachedNamespaceExtractor(
            new NamespaceExtractor(new CompilerFacade(), new TopologicalNamespaceSorter(), new SystemFileIo()),
            new NullNamespaceCache(),
            new TopologicalNamespaceSorter(),
            null,
            $cache,
        );

        $infos = $extractor->getNamespacesFromDirectories([$this->dir]);
        $cache->save();

        self::assertSame(['app.main'], array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $infos));

        $callCount = 0;
        $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)->getNamespacesFromDirectories([$this->dir]);
        self::assertSame(2, $callCount, 'The next process must walk again, not serve the skipping scan.');

        $this->expectException(CompilerException::class);
        $extractor->getNamespacesFromDirectories([$this->dir], failOnInvalidNsForm: true);
    }

    /**
     * The fingerprint records only the files a scan read, so a stored scan
     * that skipped a file with no ns form would keep hiding it after the file
     * gains one in place (#3484).
     */
    public function test_a_scan_that_skipped_a_file_with_no_ns_form_is_not_persisted(): void
    {
        Phel::bootstrap(__DIR__);
        $this->writePhel('main.phel', '(ns app.main)');
        $this->writePhel('stray.phel', "(defn x [] 1)\n");

        $cache = new PhpScanIndexCache($this->cacheFile);
        $extractor = new CachedNamespaceExtractor(
            new NamespaceExtractor(new CompilerFacade(), new TopologicalNamespaceSorter(), new SystemFileIo()),
            new NullNamespaceCache(),
            new TopologicalNamespaceSorter(),
            null,
            $cache,
        );

        $infos = $extractor->getNamespacesFromDirectories([$this->dir]);
        $cache->save();

        self::assertSame(['app.main'], array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $infos));

        $callCount = 0;
        $this->makeExtractor(new PhpScanIndexCache($this->cacheFile), $callCount)->getNamespacesFromDirectories([$this->dir]);
        self::assertSame(2, $callCount, 'The next process must walk again, not serve the skipping scan.');
    }

    public function test_a_strict_scan_reads_a_file_broken_in_the_same_second_as_the_cached_scan(): void
    {
        Phel::bootstrap(__DIR__);
        $this->writePhel('main.phel', '(ns app.main)');
        $mtime = (int) filemtime($this->dir . '/main.phel');

        $extractor = new CachedNamespaceExtractor(
            new NamespaceExtractor(new CompilerFacade(), new TopologicalNamespaceSorter(), new SystemFileIo()),
            new NullNamespaceCache(),
            new TopologicalNamespaceSorter(),
            null,
            new PhpScanIndexCache($this->cacheFile),
        );
        $extractor->getNamespacesFromDirectories([$this->dir]);

        $this->writePhel('main.phel', '(ns app.main (:require [phel.string :refer :all]))');
        touch($this->dir . '/main.phel', $mtime);
        clearstatcache();

        $this->expectException(CompilerException::class);
        $extractor->getNamespacesFromDirectories([$this->dir], failOnInvalidNsForm: true);
    }

    /**
     * Backdated, so the scan that follows does not see a file written in the
     * second it started, which it would rightly refuse to trust (#3537).
     */
    private function writePhel(string $name, string $content): void
    {
        file_put_contents($this->dir . '/' . $name, $content);
        touch($this->dir . '/' . $name, time() - 10);
    }

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

}
