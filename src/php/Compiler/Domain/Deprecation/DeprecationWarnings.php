<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Deprecation;

use Phel\Compiler\Domain\Diagnostic\ErrorNotice;
use Phel\Lang\SourceLocation;
use Phel\Shared\Facade\CompilerFacadeInterface;

use function array_pop;
use function dirname;
use function in_array;
use function sprintf;

use const E_USER_DEPRECATED;

/**
 * Process-wide switch for every `E_USER_DEPRECATED` notice the compiler
 * raises, covering both kinds of deprecation:
 *
 * - **syntax** — bare `#` comments, `#| ... |#` blocks, `|()` short fns,
 *   `,`/`,@` unquote, `$` auto-gensym, and the `\`
 *   namespace separator;
 * - **definitions** — any `def`/`defn` whose metadata carries `:deprecated`.
 *
 * Every notice is gated: off by default, on when the user asks for it via
 * `--warn-deprecations`, `PHEL_WARN_DEPRECATIONS`, or the
 * `warn-deprecations` config key. Suppressing a notice with `@` instead
 * would hide it unconditionally, so a `--warn-deprecations` run would
 * print nothing and the deprecation could never be acted on.
 *
 * This class owns the five concerns a detector would otherwise re-implement:
 * the enabled flag, the bundled-stdlib suppression, the per-`(file, subject)`
 * dedup, the syntax message shape, and the recording that lets the
 * compiled-code cache replay a compile's notices on a warm run. Detectors
 * only detect, and they ask {@see isDetecting()} rather than
 * {@see isEnabled()}: a notice has to be found while the flag is off for a
 * later `--warn-deprecations` run served from the cache to see it (#3222).
 *
 * @phpstan-import-type DeprecationRecord from CompilerFacadeInterface
 *
 * @internal
 */
final class DeprecationWarnings
{
    private static ?bool $enabled = null;

    /** @var array<string, true> */
    private static array $seen = [];

    /** @var array<string, string> */
    private static array $normalizedPaths = [];

    /**
     * One frame per compile being recorded, innermost last. A notice goes to
     * the innermost frame; a nested compile (a dependency evaluated while a
     * macro expands) does not leak its notices into the outer source.
     *
     * @var list<list<DeprecationRecord>>
     */
    private static array $recording = [];

    public static function isEnabled(): bool
    {
        return self::$enabled ??= self::readEnvFlag();
    }

    /**
     * Whether a detector should look at all: the flag is on, or a compile is
     * being recorded for the cache. Detection is cheap; what stays opt-in is
     * raising the notice.
     */
    public static function isDetecting(): bool
    {
        if (self::isEnabled()) {
            return true;
        }

        return self::$recording !== [];
    }

    /**
     * Start keeping every notice a compile finds, raised or not, so the
     * compiled-code cache can store them next to the entry and a later warm
     * run reports what the cold one did (#3222). Frames nest.
     */
    public static function startRecording(): void
    {
        self::$recording[] = [];
    }

    /**
     * @return list<DeprecationRecord>
     */
    public static function stopRecording(): array
    {
        $frame = array_pop(self::$recording);

        return $frame ?? [];
    }

    /**
     * Raise notices recorded by an earlier compile of the same source, each
     * by its own rule: an announced one always, the others when the flag is
     * on. Called on a compiled-code cache hit.
     *
     * @param list<DeprecationRecord> $records
     */
    public static function replay(array $records): void
    {
        foreach ($records as $record) {
            if ($record['announced'] || self::isEnabled()) {
                self::raise($record['message']);
            }
        }
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
        self::$recording = [];
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
        return self::isEnabled() && self::isReportableSource($sourceFile);
    }

    /**
     * Whether a deprecation in `$sourceFile` names code the user can edit,
     * independently of whether the flag is on.
     *
     * Two sources are excluded. Phel's own `src/phel`, for the reason below,
     * and anything under a `vendor/` directory: a deprecation inside a
     * dependency is the dependency author's to fix, and reporting it is how a
     * channel earns the global silencing that loses it permanently. ADR 0006
     * named this scoping as the precondition for ever announcing a deprecation
     * by default, and `announceOnceAtOrigin()` is what spends it.
     *
     * A project living under a directory literally named `vendor` is
     * suppressed too. That is the safe direction to be wrong in: a missing
     * notice, never a misdirected one.
     */
    public static function isReportableSource(string $sourceFile): bool
    {
        return $sourceFile !== ''
            && !self::isBundledStdlibSource($sourceFile)
            && !self::isThirdPartySource($sourceFile);
    }

    public static function isThirdPartySource(string $file): bool
    {
        return str_contains(self::normalizePath($file) . '/', '/vendor/');
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
        if (!self::isDetecting()) {
            return;
        }

        self::emit($message, announced: false);
    }

    /**
     * Like {@see warn()}, but silent for deprecations located in phel's
     * bundled stdlib.
     */
    public static function warnForSource(string $sourceFile, string $message): void
    {
        if (!self::isDetecting() || !self::isReportableSource($sourceFile)) {
            return;
        }

        self::emit($message, announced: false);
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
        if (!self::isDetecting() || !self::isReportableSource($sourceFile)) {
            return;
        }

        $key = $sourceFile . '|' . $subject;
        if (isset(self::$seen[$key])) {
            return;
        }

        self::$seen[$key] = true;
        self::emit($message, announced: false);
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
        self::reportOnceAtOrigin($location, $subject, $buildMessage, announced: false);
    }

    /**
     * Like {@see warnOnceAtOrigin()}, but reports whether or not the flag is
     * on. Reserved for a deprecation already scheduled for removal at the next
     * major, where an opt-in notice is no notice at all: the policy promises
     * "one full minor of warning", and a warning nobody is shown does not keep
     * that promise.
     *
     * Every other rule still applies, and they are what makes this safe: the
     * per-`(file, subject)` dedup, the expansion attribution, and the
     * first-party scoping in {@see isReportableSource()}, so a dependency's
     * code stays quiet.
     *
     * @param callable(string, int): string $buildMessage receives the file and line to report against
     */
    public static function announceOnceAtOrigin(
        SourceLocation $location,
        string $subject,
        callable $buildMessage,
    ): void {
        self::reportOnceAtOrigin($location, $subject, $buildMessage, announced: true);
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
     * file that called it. Only a detector running late enough to see a
     * stamped location can observe that; the lexer and the reader work on
     * forms the user typed, so this is a no-op for them.
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
     * @param callable(string, int): string $buildMessage
     */
    private static function reportOnceAtOrigin(
        SourceLocation $location,
        string $subject,
        callable $buildMessage,
        bool $announced,
    ): void {
        $reportAt = $location->getExpansionOrigin() ?? $location;
        $sourceFile = $reportAt->getFile();

        if (!$announced && !self::isDetecting()) {
            return;
        }

        if (!self::isReportableSource($sourceFile)) {
            return;
        }

        $key = $sourceFile . '|' . $subject;
        if (isset(self::$seen[$key])) {
            return;
        }

        self::$seen[$key] = true;
        self::emit($buildMessage($sourceFile, $reportAt->getLine()) . self::expansionSuffix($location), $announced);
    }

    /**
     * Record the notice for the compile in progress, then raise it if the
     * rule for this kind says so: announced always, the rest only with the
     * flag on. Every gate above ends here, so recording and raising can
     * never disagree about what a notice is.
     */
    private static function emit(string $message, bool $announced): void
    {
        if (self::$recording !== []) {
            $frame = array_pop(self::$recording);
            $frame[] = ['message' => $message, 'announced' => $announced];
            self::$recording[] = $frame;
        }

        if ($announced || self::isEnabled()) {
            self::raise($message);
        }
    }

    private static function raise(string $message): void
    {
        ErrorNotice::raise($message, E_USER_DEPRECATED);
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
