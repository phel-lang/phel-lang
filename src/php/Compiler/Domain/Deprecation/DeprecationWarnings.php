<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Deprecation;

use Phel\Lang\SourceLocation;

use function dirname;
use function in_array;
use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Process-wide switch for every `E_USER_DEPRECATED` notice the compiler
 * raises, covering both kinds of deprecation:
 *
 * - **syntax** — bare `#` comments, `#| ... |#` blocks, `|()` short fns,
 *   `,`/`,@` unquote, `$` auto-gensym, `^:reference` params, and the `\`
 *   namespace separator;
 * - **definitions** — any `def`/`defn` whose metadata carries `:deprecated`.
 *
 * Every notice is gated: off by default, on when the user asks for it via
 * `--warn-deprecations`, `PHEL_WARN_DEPRECATIONS`, or the
 * `warn-deprecations` config key. Suppressing a notice with `@` instead
 * would hide it unconditionally, so a `--warn-deprecations` run would
 * print nothing and the deprecation could never be acted on.
 *
 * This class owns the four concerns a detector would otherwise re-implement:
 * the enabled flag, the bundled-stdlib suppression, the per-`(file, subject)`
 * dedup, and the syntax message shape. Detectors only detect.
 */
final class DeprecationWarnings
{
    private static ?bool $enabled = null;

    /** @var array<string, true> */
    private static array $seen = [];

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
     * Drop the cached flag and the dedup table so the next `isEnabled()`
     * call re-reads the environment and every subject can warn again.
     * Intended for test `tearDown()` hooks.
     */
    public static function reset(): void
    {
        self::$enabled = null;
        self::$seen = [];
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

    /**
     * Like {@see warnForSource()}, but reports each `(sourceFile, subject)`
     * pair only once per process. Used where one subject recurs across a
     * file — a deprecated definition called fifty times, or a `\`-separated
     * symbol referenced throughout a namespace — and fifty identical lines
     * would bury the rest of the output.
     *
     * Syntax notices deliberately do NOT dedup: each occurrence sits at a
     * different line and the user has to edit every one of them.
     */
    public static function warnOnceForSource(string $sourceFile, string $subject, string $message): void
    {
        if (!self::isEnabledForSource($sourceFile)) {
            return;
        }

        $key = $sourceFile . '|' . $subject;
        if (isset(self::$seen[$key])) {
            return;
        }

        self::$seen[$key] = true;
        trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * The one message shape for a deprecated *syntax* construct:
     *
     *     Using <construct> for <purpose> is deprecated and will be removed
     *     in a future release; use <replacement> instead (at file:line:col)
     *
     * Going through a factory rather than a per-site `sprintf` keeps the
     * wording uniform and makes the "never name a concrete removal version"
     * rule structural instead of a convention: there is nowhere to put one.
     * A named release inevitably ships and the message goes stale (#2783,
     * which advertised "v0.33" until v0.48).
     *
     * @param string $construct   the deprecated syntax, pre-quoted for the reader (e.g. `'"|()"'`)
     * @param string $purpose     what the construct is used for, as a noun phrase (e.g. `'short functions'`)
     * @param string $replacement the syntax to use instead, pre-quoted (e.g. `'"#()"'`)
     */
    public static function syntaxMessage(
        string $construct,
        string $purpose,
        string $replacement,
        ?SourceLocation $location,
    ): string {
        return sprintf(
            'Using %s for %s is deprecated and will be removed in a future release; use %s instead%s',
            $construct,
            $purpose,
            $replacement,
            $location instanceof SourceLocation
                ? sprintf(' (at %s:%d:%d)', $location->getFile(), $location->getLine(), $location->getColumn())
                : '',
        );
    }

    /**
     * Builds the syntax notice with {@see syntaxMessage()} and reports it,
     * gated on the source. For callers that lex a whole file, resolve
     * {@see isEnabledForSource()} once and use {@see warn()} instead so the
     * gate is not re-evaluated per token.
     */
    public static function warnSyntax(
        string $construct,
        string $purpose,
        string $replacement,
        ?SourceLocation $location,
    ): void {
        self::warnForSource(
            $location instanceof SourceLocation ? $location->getFile() : '',
            self::syntaxMessage($construct, $purpose, $replacement, $location),
        );
    }

    private static function readEnvFlag(): bool
    {
        $flag = getenv('PHEL_WARN_DEPRECATIONS');

        return !in_array($flag, [false, '', '0'], true);
    }
}
