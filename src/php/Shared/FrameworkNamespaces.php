<?php

declare(strict_types=1);

namespace Phel\Shared;

use function str_starts_with;
use function strlen;
use function substr;

/**
 * The `phel.*` and `clojure.*` namespace space, which Phel itself provides.
 *
 * A `clojure.*` require is a compat shim for the `phel.*` namespace that holds
 * its definitions: `clojure.string` is `phel.string`, and `clojure.set`, which
 * has no Phel counterpart of its own, is `phel.core`. A `phel.*` require must
 * name a namespace Phel or an installed Phel package ships; the dependency
 * walk, the emitted `ns` form and the linter all check that against the same
 * remap, so they cannot drift on what counts as a broken require.
 */
final class FrameworkNamespaces
{
    public const string PHEL_PREFIX = 'phel.';

    public const string CLOJURE_PREFIX = 'clojure.';

    private const array CLOJURE_TARGETS = [
        'clojure.set' => 'phel.core',
    ];

    public static function matches(string $namespace): bool
    {
        $canonical = Munge::canonicalNs($namespace);

        return str_starts_with($canonical, self::PHEL_PREFIX)
            || str_starts_with($canonical, self::CLOJURE_PREFIX);
    }

    /**
     * @internal
     */
    public static function isPhel(string $namespace): bool
    {
        return str_starts_with(Munge::canonicalNs($namespace), self::PHEL_PREFIX);
    }

    /**
     * @internal
     * The `phel.*` namespace a `clojure.*` require stands for, or null when
     * the name is not in the `clojure.*` space.
     */
    public static function clojureTarget(string $namespace): ?string
    {
        $canonical = Munge::canonicalNs($namespace);
        if (!str_starts_with($canonical, self::CLOJURE_PREFIX)) {
            return null;
        }

        return self::CLOJURE_TARGETS[$canonical]
            ?? self::PHEL_PREFIX . substr($canonical, strlen(self::CLOJURE_PREFIX));
    }
}
