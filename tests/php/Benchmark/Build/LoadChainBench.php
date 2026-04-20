<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Build;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadClasspath;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * Isolated hot-path for the 24 `(load ...)` secondaries that `phel\core`
 * fires at startup. `PhelBench::bench_phel_run` folds lexing, parsing,
 * analysis, IO, and the run-command pipeline into one number; this
 * bench strips that away and calls `BuildFacade::evalFile()` directly
 * for each secondary, which is exactly what the emitted `(load ...)`
 * runtime collapses to after path resolution.
 *
 * Purpose: catch regressions in `BuildFactory` / `CompiledCodeCache` /
 * per-load overhead that would otherwise be drowned out by the
 * ~15 ms cost of actually executing the compiled PHP in
 * `bench_phel_run`.
 *
 * `@Warmup(1)` on each subject discards the first iteration — caches
 * are cold there and would otherwise pull the median upward.
 *
 * @BeforeMethods("setUp")
 */
final class LoadChainBench
{
    private const array SECONDARY_KEYS = [
        'core/meta', 'core/defs', 'core/strings', 'core/collections',
        'core/arrays', 'core/seq-basics', 'core/control', 'core/booleans',
        'core/predicates', 'core/sequences', 'core/atoms', 'core/transducers',
        'core/transients', 'core/seq-fns', 'core/fns-sets', 'core/math',
        'core/io', 'core/protocols', 'core/lazy', 'core/exceptions',
        'core/macroexpand', 'core/parsing', 'core/uuid', 'core/loops',
    ];

    /** @var list<string> */
    private array $secondaryPaths = [];

    public function setUp(): void
    {
        $projectRoot = __DIR__ . '/../../../../';

        Phel::bootstrap($projectRoot);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
        LoadClasspath::publish([$projectRoot . 'src/phel']);

        $srcPhel = $projectRoot . 'src/phel/';
        $paths = [];
        foreach (self::SECONDARY_KEYS as $key) {
            $paths[] = $srcPhel . $key . '.phel';
        }

        $this->secondaryPaths = $paths;

        // Eval the core entry point once — it fires the real
        // `(load ...)` chain which registers the namespace and warms
        // both the CompiledCodeCache and the env cache. Benchmark
        // revolutions then re-`evalFile` the same paths as cache hits.
        new BuildFacade()->evalFile($srcPhel . 'core.phel');
    }

    /**
     * Per-revolution cost of the 24-secondary chain with all caches
     * already warm. Each revolution calls `evalFile` 24 times —
     * exactly what `(load ...)` forms in compiled `phel\core`
     * dispatch to at runtime.
     *
     * @Revs(10)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_eval_core_secondaries_warm(): void
    {
        $facade = new BuildFacade();
        foreach ($this->secondaryPaths as $p) {
            $facade->evalFile($p);
        }
    }

    /**
     * Same chain via a fresh `BuildFacade` per call, the shape of
     * the code the emitter produces (`(new BuildFacade())->evalFile(
     * $path)`). Regresses first if the factory or its services
     * stop being memoised at the class-static level.
     *
     * @Revs(10)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_eval_core_secondaries_fresh_facade(): void
    {
        foreach ($this->secondaryPaths as $p) {
            new BuildFacade()->evalFile($p);
        }
    }
}
