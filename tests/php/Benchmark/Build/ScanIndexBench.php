<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Build;

use LogicException;
use Phel;
use Phel\Build\Application\CachedNamespaceExtractor;
use Phel\Build\Application\NamespaceExtractor;
use Phel\Build\Domain\Extractor\ExcludedScanPaths;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\TopologicalNamespaceSorter;
use Phel\Build\Infrastructure\Cache\NullNamespaceCache;
use Phel\Build\Infrastructure\Cache\PhpScanIndexCache;
use Phel\Build\Infrastructure\IO\SystemFileIo;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Shared\NamespaceInformation;
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

    /** @var list<string> */
    private array $phelSourceDirectories = [];

    public function setUp(): void
    {
        $projectRoot = __DIR__ . '/../../../../';
        Phel::bootstrap($projectRoot);
        GlobalEnvironmentSingleton::initializeNew();

        $this->dir = sys_get_temp_dir() . '/phel-scan-index-bench-' . uniqid();
        mkdir($this->dir, 0777, true);
        mkdir($this->dir . '/.cache');
        $this->scanIndexFile = $this->dir . '/.cache/scan-index.php';
        $this->directories = [$this->dir];

        $fixtureDirectories = [$this->dir];
        for ($i = 0; $i < self::FILE_COUNT; ++$i) {
            $sub = $this->dir . '/pkg' . intdiv($i, 25);
            if (!is_dir($sub)) {
                mkdir($sub, 0777, true);
                $fixtureDirectories[] = $sub;
            }

            $file = sprintf('%s/ns%d.phel', $sub, $i);
            file_put_contents($file, sprintf("(ns app\\pkg%d\\ns%d)\n", intdiv($i, 25), $i));
            // A file written in the second the priming scan starts is never
            // trusted, which would turn the warm subjects into cold ones.
            touch($file, time() - 10);
        }

        foreach ($fixtureDirectories as $directory) {
            touch($directory, time() - 10);
        }

        clearstatcache();
        $this->phelSourceDirectories = [$projectRoot . 'src'];

        // Prime the persisted index once so the warm subjects read it back.
        $cache = new PhpScanIndexCache($this->scanIndexFile);
        $this->makeExtractor($cache)->getNamespacesFromDirectories($this->directories);
        $this->makeExtractor($cache)->getNamespacesFromDirectories($this->phelSourceDirectories);
        $cache->save();
        $this->assertWarmScan($this->directories);
        $this->assertWarmScan($this->phelSourceDirectories);
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
     * Warm scan: a validated persisted index supplies the namespace list.
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

    /**
     * Warm scan of Phel's own `src/`, the first source dir of every run.
     * The input is the real tree on purpose: the cost under guard is its size.
     *
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_warm_scan_phel_sources(): void
    {
        $cache = new PhpScanIndexCache($this->scanIndexFile);
        $this->makeExtractor($cache)->getNamespacesFromDirectories($this->phelSourceDirectories);
    }

    /**
     * @param list<string> $directories
     */
    private function assertWarmScan(array $directories): void
    {
        $inner = new class() implements NamespaceExtractorInterface {
            public function getNamespaceFromFile(string $path): NamespaceInformation
            {
                throw new LogicException('Warm scan benchmark requires a valid persisted index: ' . $path);
            }

            public function getNamespacesFromDirectories(array $directories, bool $failOnInvalidNsForm = false): array
            {
                throw new LogicException('Warm scan benchmark unexpectedly bypassed its index.');
            }
        };
        $extractor = new CachedNamespaceExtractor(
            $inner,
            new NullNamespaceCache(),
            new TopologicalNamespaceSorter(),
            new ExcludedScanPaths(excludedDirectories: [$this->dir . '/.cache']),
            new PhpScanIndexCache($this->scanIndexFile),
        );
        if ($extractor->getNamespacesFromDirectories($directories) === []) {
            throw new LogicException('Warm scan benchmark requires a nonempty persisted index.');
        }
    }

    private function makeExtractor(PhpScanIndexCache $scanIndexCache): CachedNamespaceExtractor
    {
        $sorter = new TopologicalNamespaceSorter();
        $excludedPaths = new ExcludedScanPaths(excludedDirectories: [$this->dir . '/.cache']);
        $inner = new NamespaceExtractor(
            new CompilerFacade(),
            $sorter,
            new SystemFileIo(),
            $excludedPaths,
        );

        return new CachedNamespaceExtractor(
            $inner,
            new NullNamespaceCache(),
            $sorter,
            $excludedPaths,
            $scanIndexCache,
        );
    }
}
