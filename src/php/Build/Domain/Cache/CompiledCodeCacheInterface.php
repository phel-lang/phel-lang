<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

use Phel\Shared\Facade\CompilerFacadeInterface;

/**
 * Contract for the compiled-code cache. Lives in Domain so Application
 * collaborators (FileEvaluator, SecondaryFileHarvester, DependencyTracker)
 * can depend on the abstraction instead of the Infrastructure concrete.
 *
 * @phpstan-import-type SerializedNamespaceEnvironment from CompilerFacadeInterface
 *
 * @internal
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

    /**
     * @param list<array{message: string, announced: bool}> $deprecations the notices compiling this source found (#3222)
     */
    public function put(string $sourcePath, string $namespace, string $sourceHash, string $phpCode, array $deprecations = []): void;

    /**
     * The deprecation notices recorded when the entry for this source was
     * compiled, for a hit to replay; empty when there is no entry.
     *
     * @return list<array{message: string, announced: bool}>
     */
    public function getDeprecations(string $sourcePath): array;

    public function getCompiledPath(string $sourcePath, string $namespace): string;

    /**
     * @return SerializedNamespaceEnvironment|null
     */
    public function getEnvironment(string $namespace): ?array;

    /**
     * @param SerializedNamespaceEnvironment $envData
     */
    public function putEnvironment(string $namespace, array $envData): void;

    public function invalidate(string $sourcePath): void;

    public function clear(): void;
}
