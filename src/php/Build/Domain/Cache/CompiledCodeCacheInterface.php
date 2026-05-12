<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

/**
 * Contract for the compiled-code cache. Lives in Domain so Application
 * collaborators (FileEvaluator, SecondaryFileHarvester, DependencyTracker)
 * can depend on the abstraction instead of the Infrastructure concrete.
 */
interface CompiledCodeCacheInterface
{
    /**
     * Path of the cached compiled PHP file if it exists and matches the
     * source hash; null otherwise.
     */
    public function get(string $sourcePath, string $sourceHash): ?string;

    /**
     * True when an entry exists for this source file, regardless of
     * whether its hash matches. Distinguishes "first build" from
     * "stale cache entry".
     */
    public function has(string $sourcePath): bool;

    public function put(string $sourcePath, string $namespace, string $sourceHash, string $phpCode): void;

    public function getCompiledPath(string $sourcePath, string $namespace): string;

    /**
     * @return array<string, mixed>|null
     */
    public function getEnvironment(string $namespace): ?array;

    /**
     * @param array<string, mixed> $envData
     */
    public function putEnvironment(string $namespace, array $envData): void;

    public function invalidate(string $sourcePath): void;

    public function clear(): void;
}
