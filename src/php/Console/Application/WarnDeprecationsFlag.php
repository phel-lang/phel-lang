<?php

declare(strict_types=1);

namespace Phel\Console\Application;

use function array_filter;
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
 * Any other shape is passed through unchanged.
 *
 * @internal
 */
final class WarnDeprecationsFlag
{
    /**
     * @param list<string> $argv
     *
     * @return list<string>
     */
    public static function strip(array $argv): array
    {
        return array_values(array_filter(
            $argv,
            static fn(string $arg): bool => $arg !== '--warn-deprecations'
                && !str_starts_with($arg, '--warn-deprecations='),
        ));
    }
}
