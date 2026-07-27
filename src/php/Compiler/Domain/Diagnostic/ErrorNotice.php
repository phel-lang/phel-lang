<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Diagnostic;

use function in_array;
use function ini_get;
use function ini_set;
use function strtolower;
use function trigger_error;

/**
 * The compiler's single `trigger_error()` call, with the notice's *display*
 * pinned to stderr for its duration.
 *
 * A diagnostic must never be able to corrupt program output. The emitter builds
 * PHP source inside an `ob_start()`
 * ({@see \Phel\Compiler\Domain\Emitter\StatementEmitter}), and under PHP CLI's
 * default `display_errors=1` (STDOUT) a notice raised in there is written into
 * that buffer and spliced into the generated code. The detector that proved it
 * was the emitter's `^:reference` check, which turned `--warn-deprecations`
 * into `syntax error, unexpected token ":"` and failed the compile (#2827);
 * that alias is since removed, and no detector runs during emission today.
 *
 * The redirect stays regardless, because it closes the whole class at the
 * single point the mechanism already centralises: the next emission-time notice
 * cannot reopen it. The notice is still a real `E_USER_*`, so a userland
 * `set_error_handler` (PHPUnit's, Symfony's) sees it exactly as before — only
 * PHP's own display destination moves, and only while it is raised.
 *
 * The redirect is skipped when display is already off (setting `stderr` would
 * *enable* a notice the user silenced) or already on stderr.
 *
 * Both channels raise through here: {@see \Phel\Compiler\Domain\Deprecation\DeprecationWarnings}
 * for gated `E_USER_DEPRECATED`, {@see CompilerWarnings} for always-on
 * `E_USER_WARNING`.
 *
 * @internal
 */
final class ErrorNotice
{
    public static function raise(string $message, int $level): void
    {
        $previous = ini_get('display_errors');

        if ($previous === false || self::displayIsAlreadySafe($previous)) {
            trigger_error($message, $level);

            return;
        }

        ini_set('display_errors', 'stderr');

        try {
            trigger_error($message, $level);
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
}
