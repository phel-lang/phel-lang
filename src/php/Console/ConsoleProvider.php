<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Console\Infrastructure\Command\ApiCommands;
use Phel\Console\Infrastructure\Command\BuildCommands;
use Phel\Console\Infrastructure\Command\FormatterCommands;
use Phel\Console\Infrastructure\Command\FrameworkCommands;
use Phel\Console\Infrastructure\Command\InteropCommands;
use Phel\Console\Infrastructure\Command\LintCommands;
use Phel\Console\Infrastructure\Command\LspCommands;
use Phel\Console\Infrastructure\Command\NreplCommands;
use Phel\Console\Infrastructure\Command\ProfileCommands;
use Phel\Console\Infrastructure\Command\RunCommands;
use Phel\Console\Infrastructure\Command\WatchCommands;
use Phel\Filesystem\FilesystemFacade;
use Symfony\Component\Console\Command\Command;

final class ConsoleProvider extends AbstractProvider
{
    public const string COMMANDS = 'COMMANDS';

    public const string FACADE_FILESYSTEM = 'FACADE_FILESYSTEM';

    #[Provides(self::FACADE_FILESYSTEM)]
    public function filesystemFacade(Container $container): FilesystemFacade
    {
        return $container->getLocator()->getRequired(FilesystemFacade::class);
    }

    /**
     * Aggregates every CLI command from the per-module providers listed in
     * commandProviders(); command order follows that list. Exposed as the
     * COMMANDS dependency consumed by ConsoleBootstrap.
     *
     * @return list<Command>
     */
    #[Provides(self::COMMANDS)]
    public function commands(): array
    {
        $commands = [];
        foreach ($this->commandProviders() as $provider) {
            array_push($commands, ...$provider->commands());
        }

        return $commands;
    }

    /**
     * @return list<ConsoleCommandProviderInterface>
     */
    private function commandProviders(): array
    {
        return [
            new RunCommands(),
            new InteropCommands(),
            new FormatterCommands(),
            new ApiCommands(),
            new BuildCommands(),
            new FrameworkCommands(),
            new NreplCommands(),
            new LintCommands(),
            new ProfileCommands(),
            new LspCommands(),
            new WatchCommands(),
        ];
    }
}
