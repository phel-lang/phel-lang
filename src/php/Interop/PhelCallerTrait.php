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
 *
 * The resolution itself can fail, and the host that hits it is PHP code that may
 * never have seen a Phel stack trace, so an unresolved definition is reported as
 * {@see ExportedDefinitionNotFoundException} naming the namespace and the fix.
 *
 * @internal
 */
trait PhelCallerTrait
{
    /** @var array<string, mixed> Process-wide cache of resolved Phel definitions, keyed by "namespace::definitionName" */
    private static array $definitionCache = [];

    private static function callPhel(string $namespace, string $definitionName, mixed ...$arguments): mixed
    {
        $cacheKey = $namespace . '::' . $definitionName;

        if (!isset(self::$definitionCache[$cacheKey])) {
            // The source spelling is what an error message has to quote, the
            // munged one is what the registry is keyed by.
            $phelNs = str_replace('\\', '.', $namespace);
            self::$definitionCache[$cacheKey] = self::resolvePhelDefinition($phelNs, $definitionName);
        }

        $fn = self::$definitionCache[$cacheKey];

        return $fn(...$arguments);
    }

    private static function resolvePhelDefinition(string $phelNs, string $definitionName): mixed
    {
        $definition = Phel::getDefinition(str_replace('-', '_', $phelNs), $definitionName);

        if ($definition !== null) {
            return $definition;
        }

        if (!Phel::isNamespaceLoaded($phelNs)) {
            throw ExportedDefinitionNotFoundException::namespaceNotLoaded($phelNs, $definitionName);
        }

        throw ExportedDefinitionNotFoundException::definitionMissing($phelNs, $definitionName);
    }
}
