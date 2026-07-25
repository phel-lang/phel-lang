<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

/**
 * Null Object for {@see ScanIndexCacheInterface}, used as the default when
 * `CachedNamespaceExtractor` is constructed without a cache. It lives in
 * `Domain` rather than next to `PhpScanIndexCache` because it has no
 * infrastructure concern at all, and `Application` must not reach outward
 * into `Infrastructure` just to get a no-op.
 */
final class NullScanIndexCache implements ScanIndexCacheInterface
{
    public function get(string $dirSetKey): ?ScanIndexEntry
    {
        return null;
    }

    public function put(string $dirSetKey, array $perDir, array $infos): void
    {
        // No-op
    }

    public function clear(): void
    {
        // No-op
    }
}
