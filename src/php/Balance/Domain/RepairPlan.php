<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

use function count;
use function usort;

/**
 * The outcome of a repair search: the candidate edits the search kept (if any),
 * the cost it scored, and the reason it refused (if it did).
 *
 * @internal
 */
final readonly class RepairPlan
{
    /**
     * @param list<RepairCandidate> $candidates every parser-valid candidate the search enumerated, cheapest first
     */
    public function __construct(
        public array $candidates,
        public ?string $refusalReason = null,
    ) {}

    /**
     * @param list<RepairCandidate> $candidates
     */
    public static function refused(string $reason, array $candidates = []): self
    {
        return new self($candidates, $reason);
    }

    public function hasWinner(): bool
    {
        return $this->candidates !== [] && $this->refusalReason === null;
    }

    public function winner(): ?RepairCandidate
    {
        return $this->hasWinner() ? $this->candidates[0] : null;
    }

    /**
     * @param list<RepairCandidate> $candidates
     *
     * @return list<RepairCandidate>
     */
    public static function sortByCost(array $candidates): array
    {
        usort($candidates, static function (RepairCandidate $a, RepairCandidate $b): int {
            return $a->cost() <=> $b->cost()
                ?: count($a->edits) <=> count($b->edits);
        });

        return $candidates;
    }
}
