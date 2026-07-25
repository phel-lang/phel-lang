<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

use function array_slice;
use function count;
use function str_starts_with;

/**
 * Recovers the ini-affecting interpreter flags (`-d`, `-n`, `-c`) a user put on
 * the `php` command line, so a process that replaces its own image can hand
 * them to its successor instead of silently dropping them.
 *
 * Pure: the caller supplies the OS-reported process argv (see
 * {@see ProcessCommandLine}) and PHP's own `$_SERVER['argv']`. The script and
 * its arguments are the suffix common to both, so whatever sits between the
 * binary and that suffix is the interpreter's own option list.
 *
 * Conservative: anything that does not reconstruct cleanly — a mismatching
 * suffix (the `ps` fallback loses quoting around arguments with spaces), a
 * dangling `-d`, an option we do not model — yields `[]`, i.e. "carry nothing
 * over". Forwarding a misparsed token into the successor's argv would corrupt
 * the command; forwarding nothing merely reproduces the previous behaviour.
 */
final class UserIniFlags
{
    /**
     * @param list<string> $processArgs OS-reported argv, interpreter binary first
     * @param list<string> $scriptArgv  PHP's `$_SERVER['argv']` (script path first)
     *
     * @return list<string> flags ready to splice into an `exec` argv, or `[]` when unknown
     */
    public static function extract(array $processArgs, array $scriptArgv): array
    {
        if ($processArgs === [] || $scriptArgv === []) {
            return [];
        }

        // processArgs === [binary, ...options, ...scriptArgv]
        $optionCount = count($processArgs) - count($scriptArgv) - 1;
        if ($optionCount < 0) {
            return [];
        }

        if (array_slice($processArgs, -count($scriptArgv)) !== $scriptArgv) {
            return [];
        }

        return self::parseOptions(array_slice($processArgs, 1, $optionCount));
    }

    /**
     * @param list<string> $options
     *
     * @return list<string>
     */
    private static function parseOptions(array $options): array
    {
        $flags = [];
        $count = count($options);

        for ($i = 0; $i < $count; ++$i) {
            $option = $options[$i];

            // `-n` (ignore php.ini) changes which ini files the successor reads,
            // so it has to travel with the `-d` overrides to keep parity.
            if ($option === '-n') {
                $flags[] = $option;
                continue;
            }

            // Detached form: `-d name=value`, `-c /path/php.ini`.
            if ($option === '-d' || $option === '-c') {
                if (!isset($options[$i + 1])) {
                    return [];
                }

                $flags[] = $option;
                $flags[] = $options[++$i];
                continue;
            }

            // Glued form: `-dname=value`, `-c/path/php.ini`.
            if (str_starts_with($option, '-d') || str_starts_with($option, '-c')) {
                $flags[] = $option;
                continue;
            }

            return [];
        }

        return $flags;
    }
}
