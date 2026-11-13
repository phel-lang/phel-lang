<?php

declare(strict_types=1);

namespace Phel\Watch\Domain;

/**
 * Scans a list of paths and returns a mtime, size and content-hash snapshot
 * keyed by file path. The hash catches a same-size rewrite within one second,
 * which whole-second mtimes cannot.
 * Injected into `PollingWatcher` so tests can fake the filesystem.
 *
 * @phpstan-type FileStat array{mtime:int, size:int, hash?:string}
 *
 * @internal
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
