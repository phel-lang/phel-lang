<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use Phel\Lang\Keyword;
use PhelTest\Benchmark\Compiler\Fixtures\KeywordEmitDirect;
use PhelTest\Benchmark\Compiler\Fixtures\KeywordEmitNsScope;
use PhelTest\Benchmark\Compiler\Fixtures\KeywordEmitStatusQuo;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * Compares the three emission strategies for keyword literals inside a fn body:
 *
 *   1. status-quo: per-fn `static $__phel_kw_N; $kw ??= Keyword::create(...);`
 *      (what `BodyConstantScanner` reserves today).
 *   2. ns-scope:   shared `self::$cache[$slot] ??= Keyword::create(...);`
 *      on a parent class (issue #2130 plumbing).
 *   3. direct:     `Keyword::create(...)` per call (the bypass-facade emit
 *      shape of issue #2131, sans intermediate cache).
 *
 * All three end up returning identity-shared `Keyword` instances via
 * `Keyword::$internPool`; what differs is the per-call lookup overhead.
 *
 * @BeforeMethods("setUp")
 */
final class KeywordHoistingBench
{
    private KeywordEmitStatusQuo $statusQuoFn;

    private KeywordEmitNsScope $nsScopeFn;

    private KeywordEmitDirect $directFn;

    public function setUp(): void
    {
        $this->statusQuoFn = new KeywordEmitStatusQuo();
        $this->nsScopeFn = new KeywordEmitNsScope();
        $this->directFn = new KeywordEmitDirect();

        // Pre-warm the intern pool so the very first measured call does
        // not pay the one-shot `new Keyword(...)` cost.
        Keyword::create('alpha');
        Keyword::create('beta');
        Keyword::create('gamma');
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
