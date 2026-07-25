<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Deprecation;

use Phel\Lang\SourceLocation;

use function dirname;
use function in_array;
use function ini_get;
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

    /** @var array<string, string> */
    private static array $normalizedPaths = [];

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
        self::$normalizedPaths = [];
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
     *
     * The incoming path is resolved first: a stdlib file reached through a
     * relative prefix (`.../tests/../../src/phel/core/lazy.phel`) is the same
     * file, and a plain string prefix test would let it through.
     */
    public static function isBundledStdlibSource(string $file): bool
    {
        $normalized = self::normalizePath($file);
        $stdlibRoot = self::normalizePath(dirname(__DIR__, 4) . '/phel');

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

        self::raise($message);
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

        self::raise($message);
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
        self::raise($message);
    }

    /**
     * Like {@see warnOnceForSource()}, but attributes the notice to where the
     * construct was actually *written* rather than to where it was found.
     *
     * A form a macro or inline expansion produced carries the call site as its
     * location and the definition it came from as
     * {@see SourceLocation::getExpansionOrigin()}. Reporting the call site
     * blames an author who never wrote the construct and cannot remove it;
     * reporting the origin points at the one edit that fixes it, and lets the
     * bundled-stdlib rule silence phel's own macros instead of flooding every
     * project that calls one (#2827). An origin naming no file is unknown, and
     * silence beats misattribution.
     *
     * This is the single place that policy lives, so a detector only has to
     * hand over the location it found and say how to phrase the notice.
     *
     * @param callable(string, int): string $buildMessage receives the file and line to report against
     */
    public static function warnOnceAtOrigin(
        SourceLocation $location,
        string $subject,
        callable $buildMessage,
    ): void {
        $reportAt = $location->getExpansionOrigin() ?? $location;

        self::warnOnceForSource(
            $reportAt->getFile(),
            $subject,
            $buildMessage($reportAt->getFile(), $reportAt->getLine()) . self::expansionSuffix($location),
        );
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
     *
     * Attribution follows the same rule as {@see warnOnceAtOrigin()}: a
     * construct a macro expansion produced belongs to the macro, not to the
     * file that called it. Only the emitter's `^:reference` check runs late
     * enough to see a stamped location; the lexer and the reader work on forms
     * the user typed, so this is a no-op for them.
     */
    public static function warnSyntax(
        string $construct,
        string $purpose,
        string $replacement,
        ?SourceLocation $location,
    ): void {
        $reportAt = $location?->getExpansionOrigin() ?? $location;

        self::warnForSource(
            $reportAt instanceof SourceLocation ? $reportAt->getFile() : '',
            self::syntaxMessage($construct, $purpose, $replacement, $reportAt) . self::expansionSuffix($location),
        );
    }

    /**
     * The single `trigger_error()` call, with the notice's *display* pinned to
     * stderr for its duration.
     *
     * A diagnostic must never be able to corrupt program output, and one of
     * these detectors runs during emission — inside the `ob_start()` the
     * emitter builds PHP source with ({@see \Phel\Compiler\Domain\Emitter\StatementEmitter}).
     * Under PHP CLI's default `display_errors=1` (STDOUT) the notice text is
     * written into that buffer and spliced into the generated code, so
     * `--warn-deprecations` turned a `^:reference` param into
     * `syntax error, unexpected token ":"` and failed the compile (#2827).
     *
     * Redirecting the destination is the fix rather than moving the one
     * offending detector: it closes the whole class at the single point the
     * mechanism already centralises, so a future emission-time notice cannot
     * reopen it. The notice is still a real `E_USER_DEPRECATED`, so a userland
     * `set_error_handler` (PHPUnit's, Symfony's) sees it exactly as before —
     * only PHP's own display destination moves, and only while it is raised.
     *
     * The redirect is skipped when display is already off (setting `stderr`
     * would *enable* a notice the user silenced) or already on stderr.
     */
    private static function raise(string $message): void
    {
        $previous = ini_get('display_errors');

        if ($previous === false || self::displayIsAlreadySafe($previous)) {
            trigger_error($message, E_USER_DEPRECATED);

            return;
        }

        ini_set('display_errors', 'stderr');

        try {
            trigger_error($message, E_USER_DEPRECATED);
        } finally {
            ini_set('display_errors', $previous);
        }
    }

    /**
     * Whether PHP's own error display already cannot reach a captured stdout
     * buffer: either it is disabled, or it is pointed at stderr. The "off"
     * spellings are PHP's own ini boolean vocabulary.
     */
    private static function displayIsAlreadySafe(string $displayErrors): bool
    {
        return in_array(strtolower($displayErrors), ['', '0', 'off', 'no', 'false', 'stderr'], true);
    }

    /**
     * Names the call site an expansion was pasted at, so a notice attributed to
     * a macro still says which of the caller's lines pulled it in. Empty when
     * the form was written where it was found.
     */
    private static function expansionSuffix(?SourceLocation $location): string
    {
        if (!$location instanceof SourceLocation) {
            return '';
        }

        if (!$location->getExpansionOrigin() instanceof SourceLocation) {
            return '';
        }

        return sprintf(' (reached by expanding a macro at %s:%d)', $location->getFile(), $location->getLine());
    }

    /**
     * Canonical form of a source path for prefix comparison. Only paid for
     * once the warnings switch is on, and memoized because the same handful of
     * files come back over and over within a run. Falls back to the raw
     * (slash-normalized) string when the path names nothing on disk, which is
     * the case for in-memory sources such as the REPL's `string`.
     */
    private static function normalizePath(string $path): string
    {
        if (isset(self::$normalizedPaths[$path])) {
            return self::$normalizedPaths[$path];
        }

        $real = realpath($path);

        return self::$normalizedPaths[$path] = str_replace('\\', '/', $real === false ? $path : $real);
    }

    private static function readEnvFlag(): bool
    {
        $flag = getenv('PHEL_WARN_DEPRECATIONS');

        return !in_array($flag, [false, '', '0'], true);
    }
}
