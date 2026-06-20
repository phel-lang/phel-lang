<?php

declare(strict_types=1);

namespace Phel\Shared\Console;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * Emits a one-line deprecation notice for a CLI option that has been renamed.
 *
 * The notice goes to stderr (when available) so it never corrupts a command's
 * machine-readable stdout (e.g. `phel config --json`).
 */
final readonly class DeprecatedOptionWarner
{
    public static function warn(OutputInterface $output, string $deprecated, string $replacement): void
    {
        $stream = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;

        $stream->writeln(sprintf(
            '<comment>Warning: --%s is deprecated; use --%s instead.</comment>',
            $deprecated,
            $replacement,
        ));
    }
}
