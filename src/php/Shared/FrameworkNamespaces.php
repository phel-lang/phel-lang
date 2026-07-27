<?php

declare(strict_types=1);

namespace Phel\Shared;

use function str_starts_with;

/**
 * The `phel.*` and `clojure.*` namespace space, which Phel itself provides.
 *
 * A `(:require ...)` of one of these resolves at runtime even when a source
 * scan cannot see it: the bundled stdlib ships precompiled and lazily loaded,
 * and a `clojure.*` require is a compat shim whose referred symbols live in
 * `phel.core`. Downstream and vendored builds carry neither in their scan
 * index, so both the dependency walk and the emitted `ns` form tolerate them,
 * and only a genuinely missing *user* namespace is an error.
 */
final class FrameworkNamespaces
{
    public const string PHEL_PREFIX = 'phel.';

    public const string CLOJURE_PREFIX = 'clojure.';

    public static function matches(string $namespace): bool
    {
        $canonical = Munge::canonicalNs($namespace);

        return str_starts_with($canonical, self::PHEL_PREFIX)
            || str_starts_with($canonical, self::CLOJURE_PREFIX);
    }
}
