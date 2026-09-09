<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Diagnostic;

use function error_reporting;
use function fopen;
use function fwrite;
use function in_array;
use function ini_get;
use function ini_set;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function strtolower;
use function trigger_error;

use const E_USER_DEPRECATED;
use const PHP_EOL;

/**
 * The one place a compiler diagnostic reaches the user, written to stderr in
 * Phel's own shape.
 *
 * It used to hand every notice to `trigger_error()` and let PHP render it,
 * which cost a user three things at once: PHP writes the same notice twice
 * (once through `log_errors`, once through `display_errors`), it appends
 * `in <file> on line <n>` naming this `@internal` class rather than the code
 * the notice is about, and the result looks like a PHP error rather than a
 * Phel diagnostic (#3262).
 *
 * A userland `set_error_handler` (PHPUnit's, a project's logger) still gets
 * the notice as a real `E_USER_*`, because a handler installed to collect PHP
 * notices asked for exactly this one. That path keeps the `display_errors`
 * redirect: a handler that declines the notice hands it back to PHP, and PHP
 * CLI's default display is STDOUT, which the emitter's `ob_start()` would
 * splice into the generated code (#2827).
 *
 * Direct writes honour the user's own silencing, so `error_reporting` masking
 * the level, or both output channels being off, is still silence.
 *
 * Both channels raise through here: {@see \Phel\Compiler\Domain\Deprecation\DeprecationWarnings}
 * for gated `E_USER_DEPRECATED`, {@see CompilerWarnings} for always-on
 * `E_USER_WARNING`.
 *
 * @internal
 */
final class ErrorNotice
{
    /** PHP's own ini boolean vocabulary for "off". */
    private const array OFF_SPELLINGS = ['', '0', 'off', 'no', 'false'];

    /** @var resource|null */
    private static $stream;

    public static function raise(string $message, int $level): void
    {
        if (self::hasUserlandErrorHandler()) {
            self::triggerWithDisplayOnStderr($message, $level);

            return;
        }

        if (!self::phpWouldReport($level)) {
            return;
        }

        $stream = self::stream();
        if ($stream !== false) {
            fwrite($stream, self::format($message, $level) . PHP_EOL);
        }
    }

    /**
     * Send the next notices to `$stream` instead of stderr. Intended for test
     * `setUp()` hooks.
     *
     * @param resource $stream
     */
    public static function writeTo($stream): void
    {
        self::$stream = $stream;
    }

    /**
     * Drop a redirect installed by {@see writeTo()}. Intended for test
     * `tearDown()` hooks.
     */
    public static function reset(): void
    {
        self::$stream = null;
    }

    private static function format(string $message, int $level): string
    {
        return sprintf('%s: %s', $level === E_USER_DEPRECATED ? 'deprecated' : 'warning', $message);
    }

    /**
     * @return false|resource
     */
    private static function stream()
    {
        if (self::$stream === null) {
            // `php://stderr` rather than the `STDERR` constant, which only the
            // CLI SAPI defines.
            $opened = fopen('php://stderr', 'w');
            if ($opened === false) {
                return false;
            }

            self::$stream = $opened;
        }

        return self::$stream;
    }

    private static function hasUserlandErrorHandler(): bool
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        return $handler !== null;
    }

    /**
     * Whether PHP itself would have shown this notice anywhere: the level
     * passes `error_reporting`, and at least one output channel is on. Writing
     * regardless would turn a user's silencing into noise they cannot switch
     * off.
     */
    private static function phpWouldReport(int $level): bool
    {
        if ((error_reporting() & $level) === 0) {
            return false;
        }

        return self::iniEnabled('display_errors') || self::iniEnabled('log_errors');
    }

    private static function triggerWithDisplayOnStderr(string $message, int $level): void
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
     * buffer: either it is disabled, or it is pointed at stderr.
     */
    private static function displayIsAlreadySafe(string $displayErrors): bool
    {
        return in_array(strtolower($displayErrors), [...self::OFF_SPELLINGS, 'stderr'], true);
    }

    private static function iniEnabled(string $directive): bool
    {
        $value = ini_get($directive);

        return $value !== false && !in_array(strtolower($value), self::OFF_SPELLINGS, true);
    }
}
