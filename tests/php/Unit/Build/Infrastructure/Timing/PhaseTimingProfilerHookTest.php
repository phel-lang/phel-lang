<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Infrastructure\Timing;

use Phel\Build\Infrastructure\Timing\PhaseTimingProfilerHook;
use Phel\Lang\AbstractFn;
use PHPUnit\Framework\TestCase;

final class PhaseTimingProfilerHookTest extends TestCase
{
    public function test_record_phase_aggregates_across_sources(): void
    {
        $hook = new PhaseTimingProfilerHook();
        $hook->recordPhase('lex', 'a.phel', 1.0);
        $hook->recordPhase('analyze', 'a.phel', 4.0);
        $hook->recordPhase('lex', 'b.phel', 2.0);

        $report = $hook->report();

        self::assertSame(7.0, $report->totalMs());
        self::assertSame(2, $report->sourceCount());
        self::assertSame([
            'phases' => ['lex' => 3.0, 'analyze' => 4.0],
            'total_ms' => 7.0,
            'namespaces' => 2,
        ], $report->toArray());
    }

    public function test_wrap_fn_is_a_noop(): void
    {
        // A build evaluates `def`/`defmacro` forms while compiling. Wrapping
        // those fns in profiling proxies would bake instrumentation into the
        // emitted output, so the build-timing hook must return the fn untouched.
        $hook = new PhaseTimingProfilerHook();
        $fn = $this->createStub(AbstractFn::class);

        self::assertSame($fn, $hook->wrapFn($fn));
    }

    public function test_unused_hook_reports_empty(): void
    {
        self::assertTrue(new PhaseTimingProfilerHook()->report()->isEmpty());
    }
}
