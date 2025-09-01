<?php

declare(strict_types=1);

namespace Phel\Console\Application;

use function array_slice;
use function count;
use function in_array;

final class ArgvInputSanitizer
{
    /** @var list<string> */
    private const array RUN_OPTIONS = ['-t', '--with-time', '--clear-opcache'];

    /**
     * Normalizes `phel run` invocations so options/command/args are well-structured.
     *
     * Examples:
     *   phel run -t cmd arg1 arg2 => [script, run, -t, cmd, --, arg1, arg2]
     *   phel run --with-time      => [script, run, --with-time]
     *
     * @param list<string> $argv
     *
     * @return list<string>
     */
    public function sanitize(array $argv): array
    {
        // Nothing to do if this isn't a `run` invocation (or argv too short).
        if (($argv[1] ?? null) !== 'run') {
            return $argv;
        }

        $argc = count($argv);
        $result = [$argv[0], 'run'];

        // Cursor starts after "run"
        $i = 2;

        // Collect known run-options
        while ($i < $argc && in_array($argv[$i], self::RUN_OPTIONS, true)) {
            $result[] = $argv[$i];
            ++$i;
        }

        // The next token (if any) is the command to run
        if ($i < $argc) {
            $result[] = $argv[$i];
            ++$i;
        }

        // Any remaining tokens are passed through after a "--" separator
        if ($i < $argc) {
            $result[] = '--';
            /** @var list<string> $rest */
            $rest = array_slice($argv, $i);
            array_push($result, ...$rest);
        }

        return $result;
    }
}
