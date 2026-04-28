<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel;

trait PhelCallerTrait
{
    /** @var array<string, mixed> Cache of resolved Phel definitions */
    private static array $definitionCache = [];

    private static function callPhel(string $namespace, string $definitionName, mixed ...$arguments): mixed
    {
        $cacheKey = $namespace . '::' . $definitionName;

        if (!isset(self::$definitionCache[$cacheKey])) {
            $phelNs = str_replace('-', '_', $namespace);
            self::$definitionCache[$cacheKey] = Phel::getDefinition($phelNs, $definitionName);
        }

        $fn = self::$definitionCache[$cacheKey];

        return $fn(...$arguments);
    }
}
