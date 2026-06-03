<?php

declare(strict_types=1);

namespace Phel\Profile\Domain\Formatter;

use Phel\Profile\Domain\ProfileReport;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

final class JsonFormatter
{
    /**
     * Serialize the report to a pretty-printed JSON string with top-level keys
     * `wall_clock_ms`, `compile_phases` (per-source phase times) and `fns`
     * (per-fn records exposing both nanosecond and millisecond figures).
     */
    public function render(ProfileReport $report): string
    {
        $fns = [];
        foreach ($report->fnStats() as $boundTo => $r) {
            $fns[] = [
                'bound_to' => $boundTo,
                'calls' => $r['calls'],
                'self_ns' => $r['selfNs'],
                'total_ns' => $r['totalNs'],
                'max_ns' => $r['maxNs'],
                'self_ms' => $r['selfNs'] / 1_000_000,
                'total_ms' => $r['totalNs'] / 1_000_000,
            ];
        }

        $phases = [];
        foreach ($report->phaseMs() as $source => $byPhase) {
            $phases[] = ['source' => $source] + $byPhase;
        }

        return (string) json_encode([
            'wall_clock_ms' => $report->wallClockMs(),
            'compile_phases' => $phases,
            'fns' => $fns,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
