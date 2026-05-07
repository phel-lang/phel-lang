<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Cache;

interface DependencyTrackerInterface
{
    /**
     * @param list<string> $dependencies
     */
    public function registerDependencies(string $sourcePath, string $namespace, array $dependencies): void;
}
