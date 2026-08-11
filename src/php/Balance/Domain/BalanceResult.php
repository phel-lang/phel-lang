<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

use function array_filter;
use function array_values;
use function count;

/**
 * @internal
 */
final readonly class BalanceResult
{
    /**
     * @param list<FileOutcome> $outcomes
     */
    public function __construct(
        public array $outcomes,
    ) {}

    /**
     * @return list<FileOutcome>
     */
    public function withOutcome(BalanceOutcome $outcome): array
    {
        return array_values(array_filter(
            $this->outcomes,
            static fn(FileOutcome $fileOutcome): bool => $fileOutcome->outcome === $outcome,
        ));
    }

    public function scannedCount(): int
    {
        return count($this->outcomes);
    }
}
