<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

use function count;
use function implode;
use function sprintf;

/**
 * One repair candidate: a set of edits plus the repaired source bytes the
 * search validated by re-lexing, parsing and re-scanning.
 *
 * @internal
 */
final readonly class RepairCandidate
{
    public const int COST_INSERT = 1;

    public const int COST_REPLACE = 3;

    public const int COST_DELETE = 4;

    /**
     * @param list<RepairEdit> $edits offsets are byte offsets into {@see $original}
     */
    public function __construct(
        public string $original,
        public array $edits,
        public string $repaired,
        public bool $parserValid,
        public bool $reScanBalanced,
    ) {}

    public function cost(): int
    {
        $total = 0;
        foreach ($this->edits as $edit) {
            $total += match ($edit->kind) {
                RepairEditKind::Insert => self::COST_INSERT,
                RepairEditKind::Replace => self::COST_REPLACE,
                RepairEditKind::Delete => self::COST_DELETE,
            };
        }

        return $total;
    }

    public function editCount(): int
    {
        return count($this->edits);
    }

    public function isValid(): bool
    {
        return $this->parserValid && $this->reScanBalanced;
    }

    public function describe(): string
    {
        $lines = [];
        foreach ($this->edits as $edit) {
            $original = $edit->length > 0
                ? substr($this->original, $edit->offset, $edit->length)
                : '';
            $lines[] = match ($edit->kind) {
                RepairEditKind::Insert => sprintf("    insert '%s' at line %d, offset %d", $edit->replacement, $edit->line, $edit->offset),
                RepairEditKind::Replace => sprintf("    replace '%s' with '%s' at line %d, offset %d", $original, $edit->replacement, $edit->line, $edit->offset),
                RepairEditKind::Delete => sprintf("    delete '%s' at line %d, offset %d", $original, $edit->line, $edit->offset),
            };
        }

        return implode("\n", $lines);
    }
}
