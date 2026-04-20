<?php

declare(strict_types=1);

namespace Phel\Watch\Domain;

/**
 * Scans a list of paths and returns a mtime + size snapshot keyed by file path.
 * Injected into `PollingWatcher` so tests can fake the filesystem.
 */
interface FileSystemScannerInterface
{
    /**
     * @param list<string> $paths
     *
     * @return array<string, array{mtime:int, size:int}>
     */
    public function snapshot(array $paths): array;
}
