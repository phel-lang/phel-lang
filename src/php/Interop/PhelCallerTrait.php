<?php

declare(strict_types=1);

namespace Phel\Interop;

use Phel;

/**
 * Mixed into every generated wrapper class to resolve and invoke Phel definitions.
 *
 * Resolved definitions are memoized in a process-wide static cache shared across
 * all classes that use the trait, keyed by `namespace::definitionName`. The cache
 * is populated lazily on first call and never invalidated, so its lifetime equals
 * the process lifetime. This is safe for single-request contexts (CLI, per-request
 * FPM) but a wrapper will keep calling the originally resolved definition even if
 * the Phel runtime redefines it mid-process.
 */
trait PhelCallerTrait
{
    /** @var array<string, mixed> Process-wide cache of resolved Phel definitions, keyed by "namespace::definitionName" */
    private static array $definitionCache = [];

    private static function callPhel(string $namespace, string $definitionName, mixed ...$arguments): mixed
    {
        $cacheKey = $namespace . '::' . $definitionName;

        if (!isset(self::$definitionCache[$cacheKey])) {
            $phelNs = str_replace(['\\', '-'], ['.', '_'], $namespace);
            self::$definitionCache[$cacheKey] = Phel::getDefinition($phelNs, $definitionName);
        }

        $fn = self::$definitionCache[$cacheKey];

        return $fn(...$arguments);
    }
}
