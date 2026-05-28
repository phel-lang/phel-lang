<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use PhelTest\Benchmark\Compiler\Fixtures\CollectionEmitDirect;
use PhelTest\Benchmark\Compiler\Fixtures\CollectionEmitNsScope;
use PhelTest\Benchmark\Compiler\Fixtures\CollectionEmitStatusQuo;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * Compares the three emission strategies for collection literals inside
 * a fn body (issue #2138):
 *
 *   1. status-quo: per-fn `static $__phel_const_N; $c ??= Phel::vector(...);`
 *      — what `BodyConstantScanner` reserves today.
 *   2. ns-scope:   shared `self::$cache[$slot] ??= Phel::vector(...);`
 *      on a parent class.
 *   3. direct:     `Phel::vector(...)` per call (no caching reference).
 *
 * All three return the same persistent values; what differs is the
 * per-call lookup overhead.
 *
 * @BeforeMethods("setUp")
 */
final class CollectionHoistingBench
{
    private CollectionEmitStatusQuo $statusQuoFn;

    private CollectionEmitNsScope $nsScopeFn;

    private CollectionEmitDirect $directFn;

    public function setUp(): void
    {
        $this->statusQuoFn = new CollectionEmitStatusQuo();
        $this->nsScopeFn = new CollectionEmitNsScope();
        $this->directFn = new CollectionEmitDirect();

        // Pre-build the persistent values so the first measured call
        // does not pay the one-shot construction cost. The status-quo
        // fixture caches via static; the ns-scope fixture seeds its
        // own table from the warm-up call.
        ($this->statusQuoFn)();
        ($this->nsScopeFn)();
    }

    /**
     * @Revs(50000)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_status_quo(): void
    {
        ($this->statusQuoFn)();
    }

    /**
     * @Revs(50000)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_ns_scope(): void
    {
        ($this->nsScopeFn)();
    }

    /**
     * @Revs(50000)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_direct(): void
    {
        ($this->directFn)();
    }
}
