<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\JIT;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\Domain\Analyzer\Resolver\LoadClasspath;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * Numeric-kernel benchmarks that measure the call-boundary cost of
 * `:tag`-annotated `defn` against the same logic without annotations.
 * Each kernel is compiled once per phpbench subprocess in `setUp`,
 * then the bench subjects only invoke the resolved `AbstractFn`
 * instance — no compilation overhead enters the timing.
 *
 * Pair the harness with the OPcache JIT to see whether typed PHP
 * signatures unlock specialization on hot paths. Use the dedicated
 * Composer scripts so JIT actually engages — Xdebug hooks
 * `zend_execute_ex` and silently disables the JIT otherwise:
 *
 *     composer bench-jit-tracing   # opcache.jit=tracing, xdebug off
 *     composer bench-jit-baseline  # JIT off, same kernels for delta
 *
 * Run the comparison on a quiet machine; CI runners report unstable
 * JIT timings.
 *
 * @BeforeMethods("setUp")
 */
final class TypedSignatureBench
{
    private const string NS_UNTYPED = 'phel.bench.jit.untyped';

    private const string NS_TYPED = 'phel.bench.jit.typed';

    /** @var callable */
    private mixed $fibUntyped;

    /** @var callable */
    private mixed $fibTyped;

    /** @var callable */
    private mixed $sumSquaresUntyped;

    /** @var callable */
    private mixed $sumSquaresTyped;

    /** @var callable */
    private mixed $mandelUntyped;

    /** @var callable */
    private mixed $mandelTyped;

    public function setUp(): void
    {
        $projectRoot = __DIR__ . '/../../../../';

        Phel::bootstrap($projectRoot);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
        LoadClasspath::publish([$projectRoot . 'src/phel']);

        $facade = new BuildFacade();
        $facade->evalFile($projectRoot . 'src/phel/core.phel');
        $facade->evalFile(__DIR__ . '/Fixtures/untyped.phel');
        $facade->evalFile(__DIR__ . '/Fixtures/typed.phel');

        $registry = Registry::getInstance();
        $this->fibUntyped = $registry->getDefinition(self::NS_UNTYPED, 'fib');
        $this->fibTyped = $registry->getDefinition(self::NS_TYPED, 'fib');
        $this->sumSquaresUntyped = $registry->getDefinition(self::NS_UNTYPED, 'sum-squares');
        $this->sumSquaresTyped = $registry->getDefinition(self::NS_TYPED, 'sum-squares');
        $this->mandelUntyped = $registry->getDefinition(self::NS_UNTYPED, 'mandel-point');
        $this->mandelTyped = $registry->getDefinition(self::NS_TYPED, 'mandel-point');
    }

    /**
     * Deeply recursive: every recursive step crosses the `__invoke`
     * boundary, so typed param + return declarations are exercised
     * 2^n times per revolution. Highest expected JIT signal.
     *
     * @Revs(20)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_fib_untyped(): void
    {
        ($this->fibUntyped)(20);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_fib_typed(): void
    {
        ($this->fibTyped)(20);
    }

    /**
     * Single `__invoke` per revolution; the hot loop is internal
     * `recur`. Tag annotations only govern the call boundary, so the
     * delta against the untyped variant should be minimal — useful as
     * a control to confirm the typed signatures are not paying a tax
     * outside the recursion case.
     *
     * @Revs(200)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_sum_squares_untyped(): void
    {
        ($this->sumSquaresUntyped)(10000);
    }

    /**
     * @Revs(200)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_sum_squares_typed(): void
    {
        ($this->sumSquaresTyped)(10000);
    }

    /**
     * Float kernel, single `__invoke`, internal `recur` over a fixed
     * iteration budget. (0.25, 0.0) sits inside the set so the loop
     * runs to `max-iter` every revolution — stable timer signal.
     *
     * @Revs(2000)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_mandel_untyped(): void
    {
        ($this->mandelUntyped)(0.25, 0.0, 200);
    }

    /**
     * @Revs(2000)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_mandel_typed(): void
    {
        ($this->mandelTyped)(0.25, 0.0, 200);
    }
}
