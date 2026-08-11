<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

/**
 * @internal
 */
final readonly class FileOutcome
{
    public function __construct(
        public string $path,
        public BalanceOutcome $outcome,
        public ?BalanceReport $report = null,
        public ?string $reason = null,
    ) {}

    public static function balanced(string $path, BalanceReport $report): self
    {
        return new self($path, BalanceOutcome::Balanced, $report);
    }

    public static function repaired(string $path, BalanceReport $report): self
    {
        return new self($path, BalanceOutcome::Repaired, $report);
    }

    public static function needsRepair(string $path, BalanceReport $report): self
    {
        return new self($path, BalanceOutcome::NeedsRepair, $report);
    }

    public static function unrepairable(string $path, string $reason, ?BalanceReport $report = null): self
    {
        return new self($path, BalanceOutcome::Unrepairable, $report, $reason);
    }
}
