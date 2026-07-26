<?php

declare(strict_types=1);

namespace Phel\Console\Application;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;

use function array_filter;
use function array_values;
use function str_starts_with;

/**
 * CLI bridge for `PHEL_WARN_DEPRECATIONS`: detects the
 * `--warn-deprecations` flag in argv, turns on the process-wide
 * `DeprecationWarnings` switch (the single gate every deprecation notice
 * is checked against, syntax and definition alike), and returns argv with
 * the flag stripped so Symfony's per-command input parsers do not complain
 * about an unknown option.
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
    public static function applyAndStrip(array $argv): array
    {
        $filtered = array_values(array_filter(
            $argv,
            static fn(string $arg): bool => $arg !== '--warn-deprecations'
                && !str_starts_with($arg, '--warn-deprecations='),
        ));

        if ($filtered !== $argv) {
            DeprecationWarnings::enable();
        }

        return $filtered;
    }
}
