<?php

declare(strict_types=1);

namespace Phel\Shared;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function fclose;
use function fopen;
use function fwrite;
use function ob_end_flush;
use function ob_get_level;
use function ob_start;

/**
 * Where a command writes everything that is not its output: progress,
 * timings, notices, and what the program it loads prints. In a machine mode
 * stdout must carry only the machine output, so a tool can parse all of it.
 *
 * @internal
 */
final class StandardError
{
    public static function of(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }

    /**
     * Runs `$fn` with what it echoes sent to stderr. Symfony's console output
     * writes to its own stream, so the command's own lines are unaffected.
     *
     * @template T
     *
     * @param callable():T $fn
     *
     * @return T
     */
    public static function redirectEcho(callable $fn): mixed
    {
        $stderr = fopen('php://stderr', 'w');
        $level = ob_get_level();
        ob_start(static function (string $buffer) use ($stderr): string {
            if ($stderr !== false) {
                fwrite($stderr, $buffer);
            }

            return '';
        }, 1);

        try {
            return $fn();
        } finally {
            // A throw from evaluated code can leave its own buffer open above ours.
            while (ob_get_level() > $level) {
                ob_end_flush();
            }

            if ($stderr !== false) {
                fclose($stderr);
            }
        }
    }
}
