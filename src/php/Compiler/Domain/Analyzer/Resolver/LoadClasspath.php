<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Resolver;

use Phel;

use function is_array;

/**
 * Single source of truth for the `(load ...)` classpath — the list of
 * directories that `(load ...)` searches at runtime when a compiled
 * sibling is not found next to the caller.
 *
 * Stored in the Phel registry under a dedicated key so it does not
 * collide with other namespace-level definitions (notably the REPL's
 * private `phel\repl/src-dirs` local, which previously double-used
 * the same slot).
 *
 * The key lives under `phel\core` because `(load ...)` is a core
 * special form; the name follows Phel's earmuff convention for
 * dynamic-var-like config.
 */
final class LoadClasspath
{
    public const string NAMESPACE = 'phel\\core';

    public const string NAME = '*load-classpath*';

    /**
     * Legacy location some callers still populate directly. Kept as a
     * read-only fallback so existing programs and tests that only set
     * the old slot continue to work.
     */
    private const string LEGACY_NAMESPACE = 'phel\\repl';

    private const string LEGACY_NAME = 'src-dirs';

    /**
     * @param list<string> $directories
     */
    public static function publish(array $directories): void
    {
        Phel::addDefinition(self::NAMESPACE, self::NAME, $directories);
    }

    /**
     * @return list<string>
     */
    public static function read(): array
    {
        $value = Phel::getDefinition(self::NAMESPACE, self::NAME);
        if (is_array($value) && $value !== []) {
            /** @var list<string> $value */
            return $value;
        }

        $legacy = Phel::getDefinition(self::LEGACY_NAMESPACE, self::LEGACY_NAME);

        /** @var list<string> $result */
        $result = is_array($legacy) ? $legacy : [];

        return $result;
    }
}
