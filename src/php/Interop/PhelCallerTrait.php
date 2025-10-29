<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel;

trait PhelCallerTrait
{
    /** @var array<string, mixed> Cache of resolved Phel definitions */
    private static array $definitionCache = [];

    /**
     * @param mixed[] $arguments
     */
    private function callPhel(string $namespace, string $definitionName, ...$arguments): mixed
    {
        $cacheKey = $namespace . '::' . $definitionName;

        if (!isset(self::$definitionCache[$cacheKey])) {
            self::$definitionCache[$cacheKey] = $this->getPhelDefinition($namespace, $definitionName);
        }

        $fn = self::$definitionCache[$cacheKey];

        return $fn(...$arguments);
    }

    private function getPhelDefinition(string $namespace, string $definitionName): mixed
    {
        $phelNs = str_replace('-', '_', $namespace);

        return Phel::getDefinition($phelNs, $definitionName);
    }
}
