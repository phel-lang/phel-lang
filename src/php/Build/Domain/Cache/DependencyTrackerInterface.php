<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

interface DependencyTrackerInterface
{
    /**
     * @param list<string> $dependencies
     */
    public function registerDependencies(string $sourcePath, string $namespace, array $dependencies): void;

    /**
     * Invalidate every compiled file that depends on `$namespace`.
     *
     * @return list<string> source paths whose cache entries were invalidated
     */
    public function invalidateDependentsOf(string $namespace, CompiledCodeCacheInterface $compiledCodeCache): array;
}
