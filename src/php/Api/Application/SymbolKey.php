<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use function str_contains;

/**
 * Shared utility for building the "namespace/name" key used as both
 * the definition-index key and the reference-index key in `ProjectIndex`.
 *
 * Centralising this keeps `SymbolResolver` and `ReferenceFinder` in
 * lock-step: if one changes how symbols are keyed, the other must
 * follow.
 */
final class SymbolKey
{
    /**
     * Build the lookup key:
     * - if `$symbol` already contains `/`, use it as-is (already qualified),
     * - otherwise, qualify with `$namespace` unless empty.
     */
    public static function resolve(string $namespace, string $symbol): string
    {
        if (str_contains($symbol, '/')) {
            return $symbol;
        }

        return $namespace === '' ? $symbol : $namespace . '/' . $symbol;
    }
}
