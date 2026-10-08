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
use function set_error_handler;
use function sprintf;
use function strtolower;
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
    private const array LABELS = [
        E_WARNING => 'Warning',
        E_USER_WARNING => 'Warning',
        E_NOTICE => 'Notice',
        E_USER_NOTICE => 'Notice',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'Deprecated',
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
        $label = self::LABELS[$level] ?? null;
        if ($label === null || (error_reporting() & $level) === 0) {
            return false;
        }

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

        return true;
    }

    public function install(): void
    {
        set_error_handler($this);
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
