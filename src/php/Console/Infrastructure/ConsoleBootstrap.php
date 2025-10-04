<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Override;
use Phel\Console\ConsoleFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method ConsoleFactory getFactory()
 */
final class ConsoleBootstrap extends Application
{
    use DocBlockResolverAwareTrait;

    #[Override]
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $this->setAutoExit(false);
        $this->setDefaultCommand('repl');

        if (!$input instanceof InputInterface) {
            $input = new ArgvInput($this->getFactory()
                ->createArgvInputSanitizer()
                ->sanitize($_SERVER['argv'] ?? []));
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
}
