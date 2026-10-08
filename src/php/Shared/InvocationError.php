<?php

declare(strict_types=1);

namespace Phel\Shared;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

/**
 * A command that cannot run as asked names the problem on stderr and exits 2,
 * so stdout keeps only what the command produces.
 *
 * @internal
 */
final class InvocationError
{
    /**
     * @return int the exit code, `Command::INVALID`
     */
    public static function report(OutputInterface $output, string $message): int
    {
        $stderr = StandardError::of($output);
        $stderr->writeln(sprintf('<error>%s</error>', $message));

        return Command::INVALID;
    }
}
