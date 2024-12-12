<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Console\ConsoleFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method ConsoleFactory getFactory()
 */
final class ConsoleBootstrap extends Application
{
    use DocBlockResolverAwareTrait;

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $this->setAutoExit(false);
        $exitCode = parent::run($input, $output);
        $this->getFactory()->getFilesystemFacade()->clearAll();

        exit($exitCode);
    }

    /**
     * @return array<string,Command>
     */
    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();

        foreach ($this->getFactory()->getConsoleCommands() as $command) {
            $commands[$command->getName()] = $command;
        }

        return $commands;
    }
}
