<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Domain\Compile;

use Phel\Build\Domain\Compile\PhaseTimingReport;
use PHPUnit\Framework\TestCase;

use function array_column;

final class PhaseTimingReportTest extends TestCase
{
    public function test_empty_report(): void
    {
        $report = PhaseTimingReport::fromTotals([], 0);

        self::assertTrue($report->isEmpty());
        self::assertSame(0.0, $report->totalMs());
        self::assertSame([], $report->phases());
        self::assertSame(0, $report->sourceCount());
    }

    public function test_total_ms_sums_all_phases(): void
    {
        $report = PhaseTimingReport::fromTotals(['analyze' => 10.0, 'lex' => 2.0, 'emit' => 8.0], 3);

        self::assertFalse($report->isEmpty());
        self::assertSame(20.0, $report->totalMs());
        self::assertSame(3, $report->sourceCount());
    }

    public function test_phases_are_ordered_canonically_with_shares(): void
    {
        // Insertion order is deliberately scrambled; output must be lex -> emit.
        $report = PhaseTimingReport::fromTotals(['emit' => 5.0, 'lex' => 5.0, 'analyze' => 10.0], 1);

        $phases = $report->phases();

        self::assertSame(['lex', 'analyze', 'emit'], array_column($phases, 'phase'));
        self::assertEqualsWithDelta(25.0, $phases[0]['share'], 0.001);
        self::assertEqualsWithDelta(50.0, $phases[1]['share'], 0.001);
        self::assertEqualsWithDelta(25.0, $phases[2]['share'], 0.001);
    }

    public function test_unknown_phase_is_appended_after_known_phases(): void
    {
        $report = PhaseTimingReport::fromTotals(['custom' => 1.0, 'lex' => 1.0], 1);

        self::assertSame(['lex', 'custom'], array_column($report->phases(), 'phase'));
    }

    public function test_to_array_is_ordered_and_stable(): void
    {
        $report = PhaseTimingReport::fromTotals(['emit' => 8.0, 'lex' => 2.0], 4);

        self::assertSame([
            'phases' => ['lex' => 2.0, 'emit' => 8.0],
            'total_ms' => 10.0,
            'namespaces' => 4,
        ], $report->toArray());
    }
}
