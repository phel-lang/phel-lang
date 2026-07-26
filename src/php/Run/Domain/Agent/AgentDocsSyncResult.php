<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Agent;

use function array_merge;
use function count;

/**
 * What one `.agents/` sync did, by relative path. Also describes a `--dry-run`,
 * where the same plan is computed and nothing is written.
 */
final readonly class AgentDocsSyncResult
{
    /**
     * @param list<string> $created   absent locally, now written
     * @param list<string> $updated   ours and stale, now refreshed
     * @param list<string> $unchanged already byte-identical to what we ship
     * @param list<string> $skipped   edited locally since we installed it, left alone
     * @param list<string> $backedUp  edited locally, overwritten under `--force` after a backup
     */
    public function __construct(
        public array $created = [],
        public array $updated = [],
        public array $unchanged = [],
        public array $skipped = [],
        public array $backedUp = [],
    ) {}

    public function wroteNothing(): bool
    {
        return $this->created === [] && $this->updated === [] && $this->backedUp === [];
    }

    public function fileCount(): int
    {
        return count($this->created)
            + count($this->updated)
            + count($this->unchanged)
            + count($this->skipped)
            + count($this->backedUp);
    }

    /**
     * @return list<string>
     */
    public function writtenPaths(): array
    {
        return array_merge($this->created, $this->updated, $this->backedUp);
    }
}
