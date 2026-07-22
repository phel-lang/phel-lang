<?php

declare(strict_types=1);

namespace Phel\Watch\Domain;

/**
 * Scans a list of paths and returns a mtime + size snapshot keyed by file path.
 * Injected into `PollingWatcher` so tests can fake the filesystem.
 *
 * @phpstan-type FileStat array{mtime:int, size:int}
 */
interface FileSystemScannerInterface
{
    /**
     * @param list<string> $paths
     *
     * @return array<string, FileStat>
     */
    public function snapshot(array $paths): array;
}
