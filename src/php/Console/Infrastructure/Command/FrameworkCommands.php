<?php

declare(strict_types=1);

namespace Phel\Console\Infrastructure\Command;

use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Console\Infrastructure\Command\DebugContainerCommand;
use Gacela\Console\Infrastructure\Command\DebugDependenciesCommand;
use Gacela\Console\Infrastructure\Command\DebugModulesCommand;
use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Console\Infrastructure\Command\ProfileReportCommand;
use Gacela\Console\Infrastructure\Command\ValidateConfigCommand;
use Phel\Console\Domain\ConsoleCommandProviderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;

/**
 * @internal
 */
final class FrameworkCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('cache:warm', [], 'Pre-resolve all module classes and warm the cache for production', false, static fn(): CacheWarmCommand => self::withPhelHelp(new CacheWarmCommand())),
            new LazyCommand('debug:container', [], 'Display container debugging information (user bindings and plugins only)', false, static fn(): DebugContainerCommand => self::withPhelHelp(new DebugContainerCommand())),
            new LazyCommand('debug:dependencies', [], 'Show the constructor parameters of a class and their resolvability through the container', false, static fn(): DebugDependenciesCommand => self::withPhelHelp(new DebugDependenciesCommand())),
            new LazyCommand('debug:modules', [], 'Show dependency resolvability of every Gacela module pillar (Facade, Factory, Config, Provider)', false, static fn(): DebugModulesCommand => self::withPhelHelp(new DebugModulesCommand())),
            new LazyCommand('list:modules', [], 'Render all modules found', false, static fn(): ListModulesCommand => self::withPhelHelp(new ListModulesCommand())),
            new LazyCommand('profile:report', [], 'Display performance profiling report', false, static fn(): ProfileReportCommand => self::withPhelHelp(new ProfileReportCommand())),
            new LazyCommand('validate:config', [], 'Validate Gacela configuration for errors and best practices', false, static fn(): ValidateConfigCommand => self::withPhelHelp(new ValidateConfigCommand())),
        ];
    }

    /**
     * Gacela words its help for `bin/gacela` and a `gacela.php` file; Phel
     * users run `phel` and configure through `phel-config.php`.
     *
     * @template T of Command
     *
     * @param T $command
     *
     * @return T
     */
    private static function withPhelHelp(Command $command): Command
    {
        $help = str_replace(
            [
                'bin/gacela ',
                'file caching to be enabled in your gacela.php configuration',
                'a binding in gacela.php maps',
                'the optional gacela.php file',
            ],
            [
                'phel ',
                'file caching, which Phel enables by default',
                'a container binding maps',
                'an optional Gacela config file in the project root',
            ],
            $command->getHelp(),
        );

        return $command->setHelp($help);
    }
}
