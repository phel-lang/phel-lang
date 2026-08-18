<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

/**
 * One edit a repair search candidate makes against the source.
 *
 * `offset` is a byte offset into the original file. `length` is the number of
 * bytes the edit removes there (0 for a pure insert). `replacement` is the text
 * that takes the removed span's place (may be empty for a delete).
 *
 * @internal
 */
final readonly class RepairEdit
{
    public function __construct(
        public RepairEditKind $kind,
        public int $offset,
        public int $length,
        public string $replacement,
        public string $reason,
        public int $line = 0,
    ) {}

    public static function insert(int $offset, string $text, string $reason, int $line = 0): self
    {
        return new self(RepairEditKind::Insert, $offset, 0, $text, $reason, $line);
    }

    public static function replace(int $offset, int $length, string $text, string $reason, int $line = 0): self
    {
        return new self(RepairEditKind::Replace, $offset, $length, $text, $reason, $line);
    }

    public static function delete(int $offset, int $length, string $reason, int $line = 0): self
    {
        return new self(RepairEditKind::Delete, $offset, $length, '', $reason, $line);
    }
}
