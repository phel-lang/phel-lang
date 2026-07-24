<?php

declare(strict_types=1);

namespace Phel\Shared\Formatter;

/**
 * Outcome of formatting a batch of paths.
 *
 * A path lands in exactly one bucket: it changed (or would change under a dry
 * run), it failed (unreadable, or the source could not be lexed/parsed), or it
 * was already formatted and appears in neither list.
 */
final readonly class FormatResult
{
    /**
     * @param list<string> $changedPaths
     * @param list<string> $failedPaths
     */
    public function __construct(
        private array $changedPaths = [],
        private array $failedPaths = [],
    ) {}

    /**
     * @return list<string> paths whose contents changed (or would change under a dry run)
     */
    public function changedPaths(): array
    {
        return $this->changedPaths;
    }

    /**
     * @return list<string> paths that could not be formatted at all
     */
    public function failedPaths(): array
    {
        return $this->failedPaths;
    }

    public function hasChanges(): bool
    {
        return $this->changedPaths !== [];
    }

    public function hasFailures(): bool
    {
        return $this->failedPaths !== [];
    }
}
