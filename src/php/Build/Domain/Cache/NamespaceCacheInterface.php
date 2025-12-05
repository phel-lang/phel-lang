<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

interface NamespaceCacheInterface
{
    public function get(string $file): ?NamespaceCacheEntry;

    public function put(string $file, NamespaceCacheEntry $entry): void;

    public function remove(string $file): void;

    /**
     * @return list<string>
     */
    public function getAllFiles(): array;

    public function save(): void;

    public function clear(): void;
}
