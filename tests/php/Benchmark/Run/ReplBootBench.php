<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Run;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\LoadClasspath;
use Phel\Lang\Symbol;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

use function str_replace;

/**
 * REPL time-to-prompt benchmark.
 *
 * The REPL pays its startup cost in `NamespaceLoader::loadPhelNamespaces()`,
 * which evaluates the dependency closure of its seed namespaces before the
 * first prompt is shown. This bench measures that closure evaluation for the
 * two seed strategies so the lazy-loading win is quantifiable:
 *
 * - `bench_lazy_boot` seeds only the startup namespace + `phel.core` (the new
 *   behavior); other bundled `phel.*` modules load lazily on first reference.
 * - `bench_eager_all_boot` seeds every bundled `phel.*` module (the old
 *   behavior) for a before/after delta.
 *
 * Each iteration runs in a fresh phpbench subprocess (remote executor), so
 * `@Iterations(N)` gives N cold-start samples; the median is what we compare.
 * `@Warmup(1)` discards the first iteration so cold OPcache/method-resolution
 * caches do not skew the median.
 *
 * @BeforeMethods("setUp")
 */
final class ReplBootBench
{
    private const string PROJECT_ROOT = __DIR__ . '/../../../../';

    private const string STARTUP_NAMESPACE = 'user';

    /** @var list<string> */
    private array $srcDirectories = [];

    /** @var list<string> */
    private array $allBundledNamespaces = [];

    public function setUp(): void
    {
        Phel::bootstrap(self::PROJECT_ROOT);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $srcPhel = self::PROJECT_ROOT . 'src/phel';
        $replResources = self::PROJECT_ROOT . 'resources/repl';
        $this->srcDirectories = [$srcPhel, $replResources];
        LoadClasspath::publish($this->srcDirectories);

        $bundled = [];
        foreach ((array) glob($srcPhel . '/*.phel') as $file) {
            $bundled[] = 'phel.' . str_replace('.phel', '', basename((string) $file));
        }

        $this->allBundledNamespaces = $bundled;
    }

    /**
     * New behavior: seed only the startup namespace + `phel.core`.
     *
     * @Revs(1)
     *
     * @Iterations(10)
     *
     * @Warmup(1)
     */
    public function bench_lazy_boot(): void
    {
        $this->evaluateSeeds([self::STARTUP_NAMESPACE, 'phel.core']);
    }

    /**
     * Old behavior: seed every bundled `phel.*` module eagerly.
     *
     * @Revs(1)
     *
     * @Iterations(10)
     *
     * @Warmup(1)
     */
    public function bench_eager_all_boot(): void
    {
        $this->evaluateSeeds([self::STARTUP_NAMESPACE, 'phel.core', ...$this->allBundledNamespaces]);
    }

    /**
     * @param list<string> $seeds
     */
    private function evaluateSeeds(array $seeds): void
    {
        $buildFacade = new BuildFacade();
        foreach ($buildFacade->getDependenciesForNamespace($this->srcDirectories, $seeds) as $info) {
            $buildFacade->evalFile($info->getFile());
        }
    }
}
