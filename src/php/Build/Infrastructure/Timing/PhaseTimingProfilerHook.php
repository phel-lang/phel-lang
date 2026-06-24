<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Timing;

use Phel\Build\Domain\Compile\PhaseTimingReport;
use Phel\Lang\AbstractFn;
use Phel\Lang\ProfilerHookInterface;

use function count;

/**
 * Profiler hook installed during `phel build --timing` to sum the compiler's
 * per-phase wall-clock across every compiled namespace.
 *
 * Unlike the runtime profiler, {@see wrapFn()} is a deliberate no-op: a build
 * evaluates `def`/`defmacro` forms while compiling, and wrapping those fns in
 * profiling proxies would bake instrumentation into the build's registry and
 * emitted output. Only {@see recordPhase()} carries signal here.
 */
final class PhaseTimingProfilerHook implements ProfilerHookInterface
{
    /** @var array<string, float> */
    private array $totalsMs = [];

    /** @var array<string, true> */
    private array $sources = [];

    public function wrapFn(AbstractFn $fn): AbstractFn
    {
        return $fn;
    }

    public function recordPhase(string $phase, string $source, float $elapsedMs): void
    {
        $this->totalsMs[$phase] = ($this->totalsMs[$phase] ?? 0.0) + $elapsedMs;
        $this->sources[$source] = true;
    }

    public function report(): PhaseTimingReport
    {
        return PhaseTimingReport::fromTotals($this->totalsMs, count($this->sources));
    }
}
