<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

use Phel\Shared\NamespaceInformation;

/**
 * Persists the result of a directory scan (the grouped + topologically sorted
 * `NamespaceInformation` list) across processes, keyed by the resolved
 * directory set.
 *
 * The walk over the source tree (`RecursiveDirectoryIterator` + a `filemtime`
 * per file + topo sort) can be skipped entirely when a persisted index for the
 * same directory set is still valid. Validity is decided via
 * {@see ScanIndexEntry::isValid()} (per-directory mtime + phel-file count, plus
 * an authoritative per-file mtime check), so stale namespace information is
 * never served.
 *
 * @phpstan-import-type DirFingerprint from ScanIndexEntry
 */
interface ScanIndexCacheInterface
{
    public function get(string $dirSetKey): ?ScanIndexEntry;

    /**
     * @param array<string, DirFingerprint> $perDir
     * @param list<NamespaceInformation>    $infos
     */
    public function put(string $dirSetKey, array $perDir, array $infos): void;

    public function clear(): void;
}
