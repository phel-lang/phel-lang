<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Compile;

use function array_filter;
use function array_keys;
use function array_sum;
use function array_values;
use function in_array;

/**
 * Per-phase compile wall-clock aggregated across every namespace compiled in a
 * `phel build` run, surfaced by `phel build --timing`. It isolates the cost of
 * each compiler phase (lex/parse/read/analyze/emit) so an optimization PR can
 * quote a repeatable before/after number instead of eyeballing whole-build time.
 */
final readonly class PhaseTimingReport
{
    /** Canonical pipeline order; unknown phases are appended after these. */
    private const array PHASE_ORDER = ['lex', 'parse', 'read', 'analyze', 'emit'];

    /**
     * @param array<string, float> $totalsMs    phase name => summed milliseconds
     * @param int                  $sourceCount distinct sources (namespaces) that recorded a phase
     */
    private function __construct(
        private array $totalsMs,
        private int $sourceCount,
    ) {}

    /**
     * @param array<string, float> $totalsMs
     */
    public static function fromTotals(array $totalsMs, int $sourceCount): self
    {
        return new self($totalsMs, $sourceCount);
    }

    public function isEmpty(): bool
    {
        return $this->totalsMs === [];
    }

    public function totalMs(): float
    {
        return array_sum($this->totalsMs);
    }

    public function sourceCount(): int
    {
        return $this->sourceCount;
    }

    /**
     * Per-phase rows in canonical pipeline order, each with its summed time and
     * share of the overall compile time.
     *
     * @return list<array{phase: string, ms: float, share: float}>
     */
    public function phases(): array
    {
        $total = $this->totalMs();

        $rows = [];
        foreach ($this->orderedPhaseNames() as $phase) {
            $ms = $this->totalsMs[$phase];
            $rows[] = [
                'phase' => $phase,
                'ms' => $ms,
                'share' => $total > 0.0 ? $ms / $total * 100.0 : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * @return array{phases: array<string, float>, total_ms: float, namespaces: int}
     */
    public function toArray(): array
    {
        $phases = [];
        foreach ($this->orderedPhaseNames() as $phase) {
            $phases[$phase] = $this->totalsMs[$phase];
        }

        return [
            'phases' => $phases,
            'total_ms' => $this->totalMs(),
            'namespaces' => $this->sourceCount,
        ];
    }

    /**
     * @return list<string>
     */
    private function orderedPhaseNames(): array
    {
        $known = array_values(array_filter(
            self::PHASE_ORDER,
            fn(string $phase): bool => isset($this->totalsMs[$phase]),
        ));

        $extra = array_values(array_filter(
            array_keys($this->totalsMs),
            static fn(string $phase): bool => !in_array($phase, self::PHASE_ORDER, true),
        ));

        return [...$known, ...$extra];
    }
}
