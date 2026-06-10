<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;
use Phel\Console\Application\ArgvInputSanitizer;
use Phel\Console\Infrastructure\ConsoleBootstrap;
use Phel\Filesystem\FilesystemFacadeInterface;
use Phel\Shared\VersionResolver;
use Symfony\Component\Console\Command\Command;

/**
 * @extends AbstractFactory<AbstractConfig>
 */
final class ConsoleFactory extends AbstractFactory
{
    /**
     * Application name passed to ConsoleBootstrap; shown in the Symfony Console
     * header and the --version output.
     */
    public const string CONSOLE_NAME = 'Phel';

    public function createConsoleBootstrap(): ConsoleBootstrap
    {
        return new ConsoleBootstrap(
            self::CONSOLE_NAME,
            $this->createVersionResolver()->resolve(),
        );
    }

    /**
     * @return list<Command>
     */
    public function getConsoleCommands(): array
    {
        /** @var list<Command> $commands */
        $commands = $this->getProvidedDependency(ConsoleProvider::COMMANDS);

        return $commands;
    }

    public function getFilesystemFacade(): FilesystemFacadeInterface
    {
        /** @var FilesystemFacadeInterface $facade */
        $facade = $this->getProvidedDependency(ConsoleProvider::FACADE_FILESYSTEM);

        return $facade;
    }

    public function createVersionResolver(): VersionResolver
    {
        return new VersionResolver();
    }

    public function createArgvInputSanitizer(): ArgvInputSanitizer
    {
        return new ArgvInputSanitizer();
    }
}
