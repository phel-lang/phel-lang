<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

/**
 * Immutable snapshot of a profiling run: per-fn call stats (keyed by
 * `BOUND_TO` name, times in nanoseconds), per-source compile-phase times
 * (in milliseconds), and the total wall-clock time in milliseconds.
 *
 * @phpstan-type FnStat array{calls:int, totalNs:int, selfNs:int, maxNs:int}
 */
final readonly class ProfileReport
{
    /**
     * @param array<string, FnStat>               $fnStats
     * @param array<string, array<string, float>> $phaseMs
     */
    public function __construct(
        private array $fnStats,
        private array $phaseMs,
        private float $wallClockMs,
    ) {}

    /**
     * Per-fn stats keyed by fn name; all `*Ns` values are nanoseconds.
     *
     * @return array<string, FnStat>
     */
    public function fnStats(): array
    {
        return $this->fnStats;
    }

    /**
     * Compile-phase times keyed by source then phase name, in milliseconds.
     *
     * @return array<string, array<string, float>>
     */
    public function phaseMs(): array
    {
        return $this->phaseMs;
    }

    /**
     * Total wall-clock time of the run, in milliseconds.
     */
    public function wallClockMs(): float
    {
        return $this->wallClockMs;
    }
}
