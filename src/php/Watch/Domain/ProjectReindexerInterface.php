<?php

declare(strict_types=1);

namespace Phel\Watch\Domain;

/**
 * Re-indexes the project for editor/linter tooling after a reload cycle.
 * Abstracted here so `ReloadOrchestrator` can be unit-tested without the
 * concrete `ApiFacade`.
 */
interface ProjectReindexerInterface
{
    /**
     * @param list<string> $srcDirs
     */
    public function reindex(array $srcDirs): void;
}
