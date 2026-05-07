<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

use Phel\Build\Infrastructure\Cache\CompiledCodeCache;

interface DependencyTrackerInterface
{
    /**
     * @param list<string> $dependencies
     */
    public function registerDependencies(string $sourcePath, string $namespace, array $dependencies): void;

    /**
     * @return list<string>
     */
    public function invalidateDependentsOf(string $namespace, CompiledCodeCache $compiledCodeCache): array;
}
