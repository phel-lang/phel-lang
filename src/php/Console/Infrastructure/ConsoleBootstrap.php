<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure;

use Phel\Console\ConsoleFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

final class ConsoleBootstrap extends Application
{
    /**
     * @return array<string,Command>
     */
    protected function getDefaultCommands(): array
    {
        $commands = parent::getDefaultCommands();

        $consoleFactory = new ConsoleFactory();

        foreach ($consoleFactory->getConsoleCommands() as $command) {
            $commands[$command->getName()] = $command;
        }

        return $commands;
    }
}
