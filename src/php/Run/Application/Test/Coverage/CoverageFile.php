<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test\Coverage;

use function array_keys;
use function count;
use function ksort;
use function sort;

/**
 * Per-`.phel`-file coverage: which source lines are coverable (have compiled
 * output) and which of those executed during the test run.
 */
final readonly class CoverageFile
{
    /**
     * @param array<int, bool> $coverable phelLine => covered?
     */
    public function __construct(
        public string $filename,
        private array $coverable,
    ) {}

    public function coverableCount(): int
    {
        return count($this->coverable);
    }

    public function coveredCount(): int
    {
        $covered = 0;
        foreach ($this->coverable as $isCovered) {
            if ($isCovered) {
                ++$covered;
            }
        }

        return $covered;
    }

    public function percentage(): float
    {
        $coverable = $this->coverableCount();
        if ($coverable === 0) {
            return 100.0;
        }

        return (float) $this->coveredCount() / (float) $coverable * 100.0;
    }

    /**
     * Lines that are coverable but never executed, ascending.
     *
     * @return list<int>
     */
    public function uncoveredLines(): array
    {
        $lines = [];
        foreach ($this->coverable as $line => $isCovered) {
            if (!$isCovered) {
                $lines[] = $line;
            }
        }

        sort($lines);

        return $lines;
    }

    /**
     * @return list<int>
     */
    public function coverableLines(): array
    {
        $lines = array_keys($this->coverable);
        sort($lines);

        return $lines;
    }

    /**
     * @return array<int, bool>
     */
    public function lineHits(): array
    {
        $lines = $this->coverable;
        ksort($lines);

        return $lines;
    }
}
