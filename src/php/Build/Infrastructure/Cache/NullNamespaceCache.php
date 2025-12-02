<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;

final class NullNamespaceCache implements NamespaceCacheInterface
{
    public function get(string $file): ?NamespaceCacheEntry
    {
        return null;
    }

    public function put(string $file, NamespaceCacheEntry $entry): void
    {
        // No-op
    }

    public function remove(string $file): void
    {
        // No-op
    }

    /**
     * @return list<string>
     */
    public function getAllFiles(): array
    {
        return [];
    }

    public function save(): void
    {
        // No-op
    }

    public function clear(): void
    {
        // No-op
    }
}
