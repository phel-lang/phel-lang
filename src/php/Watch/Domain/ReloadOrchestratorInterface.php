<?php

declare(strict_types=1);

namespace Phel\Watch\Domain;

use Phel\Watch\Transfer\WatchEvent;

/**
 * Handles the "on-change" side effects: resolve namespace, reload dependency
 * chain, re-run `:on-reload` tests, publish an event, re-index for tooling.
 */
interface ReloadOrchestratorInterface
{
    /**
     * @param list<WatchEvent> $events
     * @param list<string>     $srcDirs
     *
     * @return list<string> Reloaded namespaces (in reload order)
     */
    public function handleChanges(array $events, array $srcDirs): array;
}
