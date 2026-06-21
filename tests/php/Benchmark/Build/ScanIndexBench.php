<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Build;

use Phel;
use Phel\Build\Application\CachedNamespaceExtractor;
use Phel\Build\Application\NamespaceExtractor;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Infrastructure\Cache\NullNamespaceCache;
use Phel\Build\Infrastructure\Cache\PhpScanIndexCache;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

use function sprintf;

/**
 * Isolates the directory-scan namespace-extraction path over a synthetic
 * ~500-file tree, contrasting a cold scan (full `RecursiveDirectoryIterator`
 * walk + a `filemtime` + lex/parse per file + topo sort) against a warm scan
 * served from the persisted `scan-index.php`.
 *
 * `LoadChainBench` deliberately bypasses the scan (it `evalFile`s known paths),
 * so this is the only bench that measures the walk this issue targets.
 *
 * Each revolution rebuilds a fresh `CachedNamespaceExtractor` so the intra-
 * process `directoriesScanCache` cannot mask the on-disk path — the cold/warm
 * difference is exactly the directory walk being skipped.
 *
 * @BeforeMethods("setUp")
 */
final class ScanIndexBench
{
    private const int FILE_COUNT = 500;

    private string $dir = '';

    private string $scanIndexFile = '';

    /** @var list<string> */
    private array $directories = [];

    public function setUp(): void
    {
        $projectRoot = __DIR__ . '/../../../../';
        Phel::bootstrap($projectRoot);
        GlobalEnvironmentSingleton::initializeNew();

        $this->dir = sys_get_temp_dir() . '/phel-scan-index-bench-' . uniqid();
        mkdir($this->dir, 0777, true);
        $this->scanIndexFile = $this->dir . '/.cache/scan-index.php';
        $this->directories = [$this->dir];

        for ($i = 0; $i < self::FILE_COUNT; ++$i) {
            $sub = $this->dir . '/pkg' . intdiv($i, 25);
            if (!is_dir($sub)) {
                mkdir($sub, 0777, true);
            }

            file_put_contents(
                sprintf('%s/ns%d.phel', $sub, $i),
                sprintf("(ns app\\pkg%d\\ns%d)\n", intdiv($i, 25), $i),
            );
        }

        // Prime the persisted index once so the warm subject reads it back.
        $cache = new PhpScanIndexCache($this->scanIndexFile);
        $this->makeExtractor($cache)->getNamespacesFromDirectories($this->directories);
        $cache->save();
    }

    /**
     * Cold scan: a freshly cleared persisted index forces the full directory
     * walk + per-file lex/parse + topo sort on every revolution.
     *
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_cold_scan(): void
    {
        $cache = new PhpScanIndexCache($this->scanIndexFile);
        $cache->clear();
        $this->makeExtractor($cache)->getNamespacesFromDirectories($this->directories);
    }

    /**
     * Warm scan: the persisted index validates (per-dir mtime + file count and
     * every per-file mtime), so the walk is skipped entirely.
     *
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_warm_scan(): void
    {
        $cache = new PhpScanIndexCache($this->scanIndexFile);
        $this->makeExtractor($cache)->getNamespacesFromDirectories($this->directories);
    }

    private function makeExtractor(PhpScanIndexCache $scanIndexCache): CachedNamespaceExtractor
    {
        $sorter = new TopologicalNamespaceSorter();
        $inner = new NamespaceExtractor(
            new CompilerFacade(),
            $sorter,
            new SystemFileIo(),
        );

        return new CachedNamespaceExtractor(
            $inner,
            new NullNamespaceCache(),
            $sorter,
            null,
            $scanIndexCache,
        );
    }
}
