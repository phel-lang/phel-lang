<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractFactory;
use Phel\Console\Application\VersionFinder;
use Phel\Console\Infrastructure\ConsoleBootstrap;
use Phel\Filesystem\FilesystemFacadeInterface;

final class ConsoleFactory extends AbstractFactory
{
    public const string CONSOLE_NAME = 'Phel';

    public function createConsoleBootstrap(): ConsoleBootstrap
    {
        return new ConsoleBootstrap(
            self::CONSOLE_NAME,
            $this->createVersionFinder()->getVersion(),
        );
    }

    public function getConsoleCommands(): array
    {
        return $this->getProvidedDependency(ConsoleProvider::COMMANDS);
    }

    public function getFilesystemFacade(): FilesystemFacadeInterface
    {
        return $this->getProvidedDependency(ConsoleProvider::FACADE_FILESYSTEM);
    }

    public function createVersionFinder(): VersionFinder
    {
        return new VersionFinder(
            $this->getProvidedDependency(ConsoleProvider::ROOT_PACKAGE),
        );
    }
}
