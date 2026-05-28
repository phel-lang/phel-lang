<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use PhelTest\Benchmark\Compiler\Fixtures\CallInlineDirect;
use PhelTest\Benchmark\Compiler\Fixtures\CallInlineInlined;
use PhelTest\Benchmark\Compiler\Fixtures\CallInlineStatusQuo;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * Compares the three emission strategies for a call site to a single
 * expression pure `defn` (issue #2135):
 *
 *   1. status-quo: cached `$__phel_call_N ??= \Phel::getDefinition(...)`
 *      followed by `->__invoke($arg)` on the resolved `AbstractFn`.
 *      What `CallEmitter` produces today.
 *   2. inlined:    callee body spliced at the call site with the
 *      argument substituted; no dispatch, no PHP frame.
 *   3. direct:     post-fold lower bound; the literal result that
 *      const-folding through the inlined args would collapse to.
 *
 * Per the `bench-before-perf-refactor` rule, this bench must land
 * before any inliner implementation PR so the ship decision is
 * data-driven.
 *
 * @BeforeMethods("setUp")
 */
final class CallInliningBench
{
    private CallInlineStatusQuo $statusQuoFn;

    private CallInlineInlined $inlinedFn;

    private CallInlineDirect $directFn;

    public function setUp(): void
    {
        CallInlineStatusQuo::seed();

        $this->statusQuoFn = new CallInlineStatusQuo();
        $this->inlinedFn = new CallInlineInlined();
        $this->directFn = new CallInlineDirect();

        // Warm the status-quo fn: the first call would otherwise pay
        // the one-shot registry-lookup cost the emitter front-loads via
        // `??=`. The inlined and direct subjects do no such caching.
        ($this->statusQuoFn)();
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
    public function bench_inlined(): void
    {
        ($this->inlinedFn)();
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
