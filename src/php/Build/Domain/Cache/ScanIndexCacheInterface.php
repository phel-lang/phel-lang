<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

use Phel\Shared\NamespaceInformation;

/**
 * Persists the result of a directory scan (the grouped + topologically sorted
 * `NamespaceInformation` list) across processes, keyed by the resolved
 * directory set.
 *
 * The recursive walk, namespace reads and sort can be skipped when a persisted
 * index for the same directory set is still valid. {@see ScanIndexEntry::isValid()} checks
 * indexed directory and file mtimes, rejecting racily clean stamps.
 *
 * @phpstan-import-type DirFingerprint from ScanIndexEntry
 *
 * @internal
 */
interface ScanIndexCacheInterface
{
    public function get(string $dirSetKey): ?ScanIndexEntry;

    /**
     * @param array<string, DirFingerprint> $perDir
     * @param list<NamespaceInformation>    $infos
     * @param int                           $recordedAt   the second the scan started; 0 stores an entry that never validates
     * @param list<string>                  $skippedFiles files the scan could not read a namespace from; stamped like the rest so an edit invalidates the entry
     */
    public function put(string $dirSetKey, array $perDir, array $infos, int $recordedAt = 0, array $skippedFiles = []): void;

    public function clear(): void;
}
