<?php

declare(strict_types=1);

namespace Phel\Watch\Domain;

use Phel\Watch\Transfer\WatchEvent;

/**
 * Strategy contract for the OS-specific file-watching backends.
 */
interface FileWatcherInterface
{
    /**
     * Start watching the given paths. The callback is invoked once per batch
     * of coalesced WatchEvents. Implementations block until {@see stop()} is
     * called or the watcher is otherwise terminated.
     *
     * @param list<string>                    $paths
     * @param callable(list<WatchEvent>):void $onChange
     */
    public function watch(array $paths, callable $onChange): void;

    /**
     * Request termination of an in-flight watch loop. Safe to call from a
     * signal handler or another fiber.
     */
    public function stop(): void;

    /**
     * Short human-readable backend name (e.g. `polling`, `fswatch`).
     */
    public function name(): string;
}
