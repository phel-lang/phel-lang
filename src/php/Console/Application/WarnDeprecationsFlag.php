<?php

declare(strict_types=1);

namespace Phel\Console\Application;

use function array_filter;
use function array_search;
use function array_slice;
use function array_values;
use function str_starts_with;

/**
 * CLI bridge for `PHEL_WARN_DEPRECATIONS`: strips the `--warn-deprecations`
 * flag from argv, so Symfony's per-command input parsers do not complain
 * about an unknown option.
 *
 * Stripping is all it does. Whether the flag was present is what the caller
 * needs, and turning the switch on belongs to the compiler, which owns it:
 * `ConsoleBootstrap` compares the two argv lists and asks the compiler facade
 * (#3048).
 *
 * Accepted forms: `--warn-deprecations` and `--warn-deprecations=1`.
 * Any other shape is passed through unchanged. Past the first `--` separator
 * nothing is stripped: those arguments belong to the user's script.
 *
 * @internal
 */
final class WarnDeprecationsFlag
{
    public static function matches(string $arg): bool
    {
        return $arg === '--warn-deprecations' || str_starts_with($arg, '--warn-deprecations=');
    }

    /**
     * @param list<string> $argv
     *
     * @return list<string>
     */
    public static function strip(array $argv): array
    {
        $separator = array_search('--', $argv, true);
        $options = $separator === false ? $argv : array_slice($argv, 0, $separator);
        $forwarded = $separator === false ? [] : array_slice($argv, $separator);

        return [
            ...array_values(array_filter($options, static fn(string $arg): bool => !self::matches($arg))),
            ...$forwarded,
        ];
    }
}
