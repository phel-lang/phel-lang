<?php

declare(strict_types=1);

namespace Phel\Run\Infrastructure\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * The single declaration of `--stack-trace`, shared by `run`, `eval`, `repl`
 * and `test`.
 *
 * Traces collapse Phel's own frames by default (#1910). This is the override:
 * one spelling and one description, so a user who learns the flag on one
 * command finds the same flag on the others.
 *
 * @internal
 */
final readonly class StackTraceOption
{
    public const string NAME = 'stack-trace';

    private const string DESCRIPTION = 'Show every stack frame, including the internal ones collapsed by default.';

    public static function addTo(Command $command): void
    {
        $command->addOption(self::NAME, null, InputOption::VALUE_NONE, self::DESCRIPTION);
    }

    public static function isEnabled(InputInterface $input): bool
    {
        return (bool) $input->getOption(self::NAME);
    }
}
