<?php

declare(strict_types=1);

namespace Phel\Compiler\Infrastructure\Cache;

use Phel\Compiler\Domain\Cache\ReaderResultCacheInterface;

/**
 * No-op cache used whenever the intermediate-artifact cache is disabled
 * (the default). Every load misses, so the full pipeline always runs.
 */
final class NullReaderResultCache implements ReaderResultCacheInterface
{
    public function load(string $phelCode, int $optimizationLevel): ?array
    {
        return null;
    }

    public function save(string $phelCode, int $optimizationLevel, array $entries): void {}
}
