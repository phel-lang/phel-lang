<?php

declare(strict_types=1);

namespace Phel\Lang;

use function is_array;

/**
 * Single source of truth for the `(load ...)` classpath — the list of
 * directories that `(load ...)` searches at runtime when a compiled
 * sibling is not found next to the caller.
 *
 * Stored in the Phel registry under a dedicated key so it does not
 * collide with other namespace-level definitions.
 *
 * The key lives under `phel\core` because `(load ...)` is a core
 * special form; the name follows Phel's earmuff convention for
 * dynamic-var-like config.
 */
final class LoadClasspath
{
    public const string NAMESPACE = 'phel.core';

    public const string NAME = '*load-classpath*';

    /**
     * Process-local memo of the last resolved classpath. The registry is the
     * authoritative store; this cache just avoids repeated `__callStatic`
     * dispatch when the same runtime lookup fires many times in a row
     * (notably during core startup where 24 `(load ...)` forms each resolve
     * the classpath).
     *
     * @var list<string>|null
     */
    private static ?array $cached = null;

    /**
     * @param list<string> $directories
     */
    public static function publish(array $directories): void
    {
        self::$cached = null;
        Registry::getInstance()->addDefinition(self::NAMESPACE, self::NAME, $directories);
    }

    /**
     * @return list<string>
     */
    public static function read(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $value = Registry::getInstance()->getDefinition(self::NAMESPACE, self::NAME);

        /** @var list<string> $result */
        $result = is_array($value) ? $value : [];

        return self::$cached = $result;
    }
}
