<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PhpBench\Benchmark\Metadata\Annotations\AfterMethods;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * Startup benchmarks. Each iteration runs in a fresh phpbench subprocess
 * (remote executor default), so `@Iterations(N)` gives N cold-start
 * samples — the median of which is what we compare. `@Revs(1)` is
 * correct here: these measure end-to-end one-shot work, not a tight
 * hot loop that would benefit from amortising timer overhead.
 *
 * `@Warmup(1)` discards the first iteration — OPcache + method-resolution
 * caches are cold there and would otherwise skew the median upward.
 *
 * @BeforeMethods("setUp")
 *
 * @AfterMethods("tearDown")
 */
final class PhelBench
{
    /** @var list<string> */
    private array $tempPaths = [];

    public function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            $this->newTempPath(),
        );
    }

    public function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->tempPaths = [];
    }

    /**
     * End-to-end runtime cost of booting `phel\core` via `Phel::run`
     * (evalFile of core + every dependency triggered through
     * `(load ...)`). 10 samples give a usable median under ~±2 %
     * variance on a quiet machine.
     *
     * @Revs(1)
     *
     * @Iterations(10)
     *
     * @Warmup(1)
     */
    public function bench_phel_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\core');
    }

    /**
     * Cold compile of `phel\core` to PHP. Heavier than `bench_phel_run`
     * (~half a second on the monolithic layout, ~30 ms on the split
     * layout) so we use fewer iterations to keep the suite fast.
     *
     * @Revs(1)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_core_file_compilation(): void
    {
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            $this->newTempPath(),
        );
    }

    private function newTempPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phel-core');
        $this->tempPaths[] = $path;

        return $path;
    }
}
