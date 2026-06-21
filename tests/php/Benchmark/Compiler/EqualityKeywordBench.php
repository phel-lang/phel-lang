<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use Phel\Lang\Keyword;
use PhelTest\Benchmark\Compiler\Fixtures\EqualityKeywordNative;
use PhelTest\Benchmark\Compiler\Fixtures\EqualityKeywordRuntime;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

/**
 * Compares the two emission strategies for a `(= x :kw)`-heavy `cond`
 * chain (issue #2561):
 *
 *   1. runtime: each comparison dispatches through `phel.core/=`
 *      (`Equalizer::equals`) — the status quo.
 *   2. native:  each comparison lowers to a native PHP `===` against a
 *      hoisted interned keyword constant — the optimization.
 *
 * Both return the same result for every keyword input; what differs is
 * the per-site dispatch overhead.
 *
 * @BeforeMethods("setUp")
 */
final class EqualityKeywordBench
{
    private EqualityKeywordRuntime $runtimeFn;

    private EqualityKeywordNative $nativeFn;

    private Keyword $miss;

    public function setUp(): void
    {
        $this->runtimeFn = new EqualityKeywordRuntime();
        $this->nativeFn = new EqualityKeywordNative();

        // Worst case for a cond chain: the value matches none of the
        // branches, so every comparison runs.
        $this->miss = Keyword::create('omega');
    }

    /**
     * @Revs(50000)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_runtime_dispatch(): void
    {
        ($this->runtimeFn)($this->miss);
    }

    /**
     * @Revs(50000)
     *
     * @Iterations(10)
     *
     * @Warmup(2)
     */
    public function bench_native_identity(): void
    {
        ($this->nativeFn)($this->miss);
    }
}
