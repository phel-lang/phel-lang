<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Diagnostic;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;

use const E_USER_WARNING;

/**
 * Always-on `E_USER_WARNING` channel for compiler diagnostics that are not
 * deprecations.
 *
 * The only difference from {@see DeprecationWarnings} is the gate, and that is
 * the whole point: a deprecation is advice the user opts into with
 * `--warn-deprecations`, while a name collision has already changed which
 * definition the program calls, so staying quiet is itself the bug (#2897).
 *
 * Like its sibling it owns the concerns a detector would otherwise
 * re-implement: the bundled-stdlib suppression and the per-`(file, subject)`
 * dedup. Detectors only detect.
 *
 * @internal
 */
final class CompilerWarnings
{
    /** @var array<string, true> */
    private static array $seen = [];

    /**
     * Reports each `(sourceFile, subject)` pair once per process, and never for
     * phel's own bundled stdlib: a user cannot edit that, so a warning there
     * would only bury the ones about their code.
     */
    public static function warnOnceForSource(string $sourceFile, string $subject, string $message): void
    {
        if ($sourceFile === '' || DeprecationWarnings::isBundledStdlibSource($sourceFile)) {
            return;
        }

        $key = $sourceFile . '|' . $subject;
        if (isset(self::$seen[$key])) {
            return;
        }

        self::$seen[$key] = true;
        ErrorNotice::raise($message, E_USER_WARNING);
    }

    /**
     * Drop the dedup table so every subject can warn again. Intended for test
     * `tearDown()` hooks.
     */
    public static function reset(): void
    {
        self::$seen = [];
    }
}
