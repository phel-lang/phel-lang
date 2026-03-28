<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Override;
use Phel\Console\ConsoleFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_slice;
use function in_array;

#[ServiceMap(method: 'getFactory', className: ConsoleFactory::class)]
final class ConsoleBootstrap extends Application
{
    use ServiceResolverAwareTrait;

    #[Override]
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $this->setAutoExit(false);

        $sanitizedArgs = $this->getFactory()
            ->createArgvInputSanitizer()
            ->sanitize($_SERVER['argv'] ?? []);

        $this->setDefaultCommand('repl');

        if ($this->isTopLevelHelp($sanitizedArgs)) {
            $sanitizedArgs = $this->replaceHelpWithList($sanitizedArgs);
        }

        if (!$input instanceof InputInterface) {
            $input = new ArgvInput($sanitizedArgs);
        }

        $exitCode = parent::run($input, $output);
        $this->getFactory()->getFilesystemFacade()->clearAll();

        exit($exitCode);
    }

    /**
     * @return array<string,Command>
     */
    #[Override]
    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();

        foreach ($this->getFactory()->getConsoleCommands() as $command) {
            $commands[$command->getName()] = $command;
        }

        return $commands;
    }

    /**
     * Detect when --help/-h is requested without an explicit command,
     * so we show top-level help listing all commands instead of repl help.
     */
    private function isTopLevelHelp(array $argv): bool
    {
        $args = array_slice($argv, 1);

        $hasHelp = in_array('--help', $args, true) || in_array('-h', $args, true);
        if (!$hasHelp) {
            return false;
        }

        foreach ($args as $arg) {
            if ($arg === '--help') {
                continue;
            }

            if ($arg === '-h') {
                continue;
            }

            if (!str_starts_with((string) $arg, '-')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strip --help/-h flags and insert 'list' as the command,
     * so Symfony Console executes the list command directly.
     */
    private function replaceHelpWithList(array $argv): array
    {
        $filtered = array_values(array_filter(
            $argv,
            static fn($arg): bool => $arg !== '--help' && $arg !== '-h',
        ));

        array_splice($filtered, 1, 0, ['list']);

        return $filtered;
    }
}
