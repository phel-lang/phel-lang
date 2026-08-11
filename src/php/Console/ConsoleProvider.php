<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Phel\Console\Infrastructure\Command\ApiCommands;
use Phel\Console\Infrastructure\Command\BalanceCommands;
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
use Phel\Filesystem\FilesystemFacadeInterface;
use Symfony\Component\Console\Command\LazyCommand;

/**
 * @internal
 */
#[ServiceMap(method: 'getConfig', className: AbstractConfig::class)]
final class ConsoleProvider extends AbstractProvider
{
    public const string LAZY_COMMANDS = 'LAZY_COMMANDS';

    #[Provides(FilesystemFacadeInterface::class)]
    public function filesystemFacade(Container $container): FilesystemFacadeInterface
    {
        return $container->getLocator()->getRequired(FilesystemFacade::class);
    }

    /**
     * Aggregates the lazily-instantiated CLI commands from the per-module
     * providers listed in commandProviders(); command order follows that list.
     * Exposed as the LAZY_COMMANDS dependency consumed by ConsoleBootstrap to
     * build the lazy command loader, so only the dispatched command is
     * instantiated per invocation while list/help metadata stays available.
     *
     * @return list<LazyCommand>
     */
    #[Provides(self::LAZY_COMMANDS)]
    public function lazyCommands(): array
    {
        $commands = [];
        foreach ($this->commandProviders() as $provider) {
            foreach ($provider->lazyCommands() as $command) {
                $commands[] = $command;
            }
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
            new BalanceCommands(),
            new ProfileCommands(),
            new LspCommands(),
            new WatchCommands(),
        ];
    }
}
