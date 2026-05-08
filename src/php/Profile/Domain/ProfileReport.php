<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

final readonly class ProfileReport
{
    /**
     * @param array<string, array{calls:int, totalNs:int, selfNs:int, maxNs:int}> $fnStats
     * @param array<string, array<string, float>>                                 $phaseMs
     */
    public function __construct(
        private array $fnStats,
        private array $phaseMs,
        private float $wallClockMs,
    ) {}

    /**
     * @return array<string, array{calls:int, totalNs:int, selfNs:int, maxNs:int}>
     */
    public function fnStats(): array
    {
        return $this->fnStats;
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function phaseMs(): array
    {
        return $this->phaseMs;
    }

    public function wallClockMs(): float
    {
        return $this->wallClockMs;
    }
}
