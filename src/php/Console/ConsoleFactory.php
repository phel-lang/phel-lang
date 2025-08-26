<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractFactory;
use Phel\Console\Infrastructure\ConsoleBootstrap;
use Phel\Filesystem\FilesystemFacadeInterface;

final class ConsoleFactory extends AbstractFactory
{
    public function getConsoleCommands(): array
    {
        return $this->getProvidedDependency(ConsoleProvider::COMMANDS);
    }

    public function getFilesystemFacade(): FilesystemFacadeInterface
    {
        return $this->getProvidedDependency(ConsoleProvider::FACADE_FILESYSTEM);
    }

    public function createConsoleBootstrap(): ConsoleBootstrap
    {
        return new ConsoleBootstrap(name: 'Phel', version: 'v0.20.0');
    }
}
