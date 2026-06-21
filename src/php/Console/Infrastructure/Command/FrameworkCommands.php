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
use Symfony\Component\Console\Command\LazyCommand;

final class FrameworkCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('cache:warm', [], 'Pre-resolve all module classes and warm the cache for production', false, static fn(): CacheWarmCommand => new CacheWarmCommand()),
            new LazyCommand('debug:container', [], 'Display container debugging information (user bindings and plugins only)', false, static fn(): DebugContainerCommand => new DebugContainerCommand()),
            new LazyCommand('debug:dependencies', [], 'Show the constructor parameters of a class and their resolvability through the container', false, static fn(): DebugDependenciesCommand => new DebugDependenciesCommand()),
            new LazyCommand('debug:modules', [], 'Show dependency resolvability of every Gacela module pillar (Facade, Factory, Config, Provider)', false, static fn(): DebugModulesCommand => new DebugModulesCommand()),
            new LazyCommand('list:modules', [], 'Render all modules found', false, static fn(): ListModulesCommand => new ListModulesCommand()),
            new LazyCommand('profile:report', [], 'Display performance profiling report', false, static fn(): ProfileReportCommand => new ProfileReportCommand()),
            new LazyCommand('validate:config', [], 'Validate Gacela configuration for errors and best practices', false, static fn(): ValidateConfigCommand => new ValidateConfigCommand()),
        ];
    }
}
