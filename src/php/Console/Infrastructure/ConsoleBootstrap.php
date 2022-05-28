<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Console\ConsoleFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * @method ConsoleFactory getFactory()
 */
final class ConsoleBootstrap extends Application
{
    use DocBlockResolverAwareTrait;

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
