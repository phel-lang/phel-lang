<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Deprecation;

use function dirname;
use function in_array;

use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Process-wide switch for the compiler's own `E_USER_DEPRECATED` notices
 * about deprecated Phel syntax (bare `#` comments, `#| ... |#` blocks,
 * `|()` short fns, `,`/`,@` unquote, `$` auto-gensym, `\` namespace
 * separators).
 *
 * Every notice is gated: off by default, on when the user asks for it via
 * `--warn-deprecations`, `PHEL_WARN_DEPRECATIONS`, or the
 * `warn-deprecations` config key. Suppressing a notice with `@` instead
 * would hide it unconditionally, so a `--warn-deprecations` run would
 * print nothing and the deprecation could never be acted on.
 */
final class DeprecationWarnings
{
    private static ?bool $enabled = null;

    public static function isEnabled(): bool
    {
        return self::$enabled ??= self::readEnvFlag();
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Drop the cached flag so the next `isEnabled()` call re-reads the
     * environment. Intended for test `tearDown()` hooks.
     */
    public static function reset(): void
    {
        self::$enabled = null;
    }

    /**
     * Whether a deprecation found in `$sourceFile` should be reported.
     * Bundled-stdlib sources are excluded: a user cannot act on deprecated
     * syntax inside phel's own `src/phel`, so warning about it would bury
     * the notices that do concern their code.
     *
     * Callers scanning many tokens from one source should call this once
     * and reuse the result instead of paying for it per token.
     */
    public static function isEnabledForSource(string $sourceFile): bool
    {
        return self::isEnabled()
            && $sourceFile !== ''
            && !self::isBundledStdlibSource($sourceFile);
    }

    /**
     * Source-path suppression for phel's bundled stdlib. The path is
     * anchored to this package's own `src/phel`, so nested-layout user
     * projects with their own `src/phel` still receive warnings.
     */
    public static function isBundledStdlibSource(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);
        $stdlibRoot = str_replace('\\', '/', dirname(__DIR__, 4) . '/phel');

        return $normalized === $stdlibRoot
            || str_starts_with($normalized, $stdlibRoot . '/');
    }

    /**
     * Emits `$message` as an `E_USER_DEPRECATED` notice when deprecation
     * warnings are enabled, and does nothing otherwise.
     */
    public static function warn(string $message): void
    {
        if (!self::isEnabled()) {
            return;
        }

        trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * Like {@see warn()}, but silent for deprecations located in phel's
     * bundled stdlib.
     */
    public static function warnForSource(string $sourceFile, string $message): void
    {
        if (!self::isEnabledForSource($sourceFile)) {
            return;
        }

        trigger_error($message, E_USER_DEPRECATED);
    }

    private static function readEnvFlag(): bool
    {
        $flag = getenv('PHEL_WARN_DEPRECATIONS');

        return !in_array($flag, [false, '', '0'], true);
    }
}
