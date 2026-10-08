<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

use Closure;

use function error_log;
use function error_reporting;
use function fclose;
use function fopen;
use function fwrite;
use function in_array;
use function ini_get;
use function ini_set;
use function set_error_handler;
use function sprintf;
use function strtolower;
use function trigger_error;
use function trim;

use const E_DEPRECATED;
use const E_NOTICE;
use const E_USER_DEPRECATED;
use const E_USER_NOTICE;
use const E_USER_WARNING;
use const E_WARNING;
use const PHP_EOL;

/**
 * Reports a PHP warning, notice or deprecation raised by compiled Phel code
 * the way PHP would, but naming the `.phel` file and line instead of the eval
 * temp file or compiled cache (#3537). Anything it cannot map goes back to
 * PHP's own handler, unchanged.
 *
 * @internal
 */
final readonly class SourceMappedErrorHandler
{
    /** Each level's label, and the user level that records it as the last error. */
    private const array LEVELS = [
        E_WARNING => ['Warning', E_USER_WARNING],
        E_USER_WARNING => ['Warning', E_USER_WARNING],
        E_NOTICE => ['Notice', E_USER_NOTICE],
        E_USER_NOTICE => ['Notice', E_USER_NOTICE],
        E_DEPRECATED => ['Deprecated', E_USER_DEPRECATED],
        E_USER_DEPRECATED => ['Deprecated', E_USER_DEPRECATED],
    ];

    /**
     * `$sourceOf` returns the `.phel` file and line a compiled file and line
     * came from, or null when it has none.
     *
     * @param Closure(string, int): (array{0: string, 1: int}|null) $sourceOf
     */
    public function __construct(
        private Closure $sourceOf,
    ) {}

    public function __invoke(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (!isset(self::LEVELS[$level]) || !$this->wouldReport($level)) {
            return false;
        }

        [$label, $userLevel] = self::LEVELS[$level];

        $source = ($this->sourceOf)($file, $line);
        if ($source === null) {
            return false;
        }

        $text = sprintf('%s in %s on line %d', $message, $source[0], $source[1]);

        if ($this->iniEnabled('log_errors')) {
            error_log(sprintf('PHP %s:  %s', $label, $text));
        }

        if ($this->iniEnabled('display_errors')) {
            $stream = fopen($this->displayStream(), 'w');
            if ($stream !== false) {
                fwrite($stream, sprintf('%s%s: %s%s', PHP_EOL, $label, $text, PHP_EOL));
                fclose($stream);
            }
        }

        $this->recordAsLastError($message, $userLevel);

        return true;
    }

    public function install(): void
    {
        set_error_handler($this);
    }

    /**
     * Whether PHP itself would show or log this level. When it would not,
     * PHP handles it, which also skips the source lookup.
     */
    private function wouldReport(int $level): bool
    {
        if ((error_reporting() & $level) === 0) {
            return false;
        }

        return $this->iniEnabled('display_errors') || $this->iniEnabled('log_errors');
    }

    /**
     * A handler that returns true keeps PHP from recording the error, and
     * Phel code reads failures through `error_get_last()`. PHP does not call
     * a handler from inside itself, so this records the message without
     * printing it again. The recorded type is the user level and the file
     * is this one.
     */
    private function recordAsLastError(string $message, int $userLevel): void
    {
        $display = ini_set('display_errors', '0');
        $log = ini_set('log_errors', '0');

        try {
            trigger_error($message, $userLevel);
        } finally {
            if ($display !== false) {
                ini_set('display_errors', $display);
            }

            if ($log !== false) {
                ini_set('log_errors', $log);
            }
        }
    }

    /**
     * PHP's CLI display goes to stdout, through any output buffer, unless
     * `display_errors=stderr`.
     */
    private function displayStream(): string
    {
        return strtolower(trim((string) ini_get('display_errors'))) === 'stderr' ? 'php://stderr' : 'php://output';
    }

    private function iniEnabled(string $directive): bool
    {
        $value = strtolower(trim((string) ini_get($directive)));

        return !in_array($value, ['', '0', 'off', 'no', 'false'], true);
    }
}
