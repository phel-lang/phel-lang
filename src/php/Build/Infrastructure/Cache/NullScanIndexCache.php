<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Phel\Build\Domain\Cache\ScanIndexCacheInterface;
use Phel\Build\Domain\Cache\ScanIndexEntry;

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
