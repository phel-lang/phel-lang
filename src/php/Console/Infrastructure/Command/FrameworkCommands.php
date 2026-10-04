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

/**
 * @internal
 */
final class FrameworkCommands implements ConsoleCommandProviderInterface
{
    public function lazyCommands(): array
    {
        return [
            new LazyCommand('cache:warm', [], 'Pre-resolve all module classes and warm the cache for production', false, static fn(): CacheWarmCommand => self::cacheWarmCommand()),
            new LazyCommand('debug:container', [], 'Display container debugging information (user bindings and plugins only)', false, static fn(): DebugContainerCommand => new DebugContainerCommand()),
            new LazyCommand('debug:dependencies', [], 'Show the constructor parameters of a class and their resolvability through the container', false, static fn(): DebugDependenciesCommand => new DebugDependenciesCommand()),
            new LazyCommand('debug:modules', [], 'Show dependency resolvability of every Gacela module pillar (Facade, Factory, Config, Provider)', false, static fn(): DebugModulesCommand => new DebugModulesCommand()),
            new LazyCommand('list:modules', [], 'Render all modules found', false, static fn(): ListModulesCommand => new ListModulesCommand()),
            new LazyCommand('profile:report', [], 'Display performance profiling report', false, static fn(): ProfileReportCommand => new ProfileReportCommand()),
            new LazyCommand('validate:config', [], 'Validate Gacela configuration for errors and best practices', false, static fn(): ValidateConfigCommand => new ValidateConfigCommand()),
        ];
    }

    /**
     * Gacela words its own help for `bin/gacela` and a `gacela.php` file;
     * Phel users run `phel` and always have the file cache enabled.
     */
    private static function cacheWarmCommand(): CacheWarmCommand
    {
        return new CacheWarmCommand()->setHelp(<<<'HELP'
This command pre-resolves all module classes (Facades, Factories, Configs, and Providers)
and populates the module cache for optimal production performance.

<info>What it does:</info>
  - Discovers all modules in your application
  - Resolves each module's Facade, Factory, Config, and Provider classes
  - Generates optimized cache files for class resolution
  - Optionally pre-scans #[ServiceMap] attributes to avoid reflection overhead,
    and the #[Plugin] classes that join plugin stacks to avoid a scan at runtime
  - Reports statistics about the warming process

<info>When to use:</info>
  - During deployment to production
  - After adding new modules
  - After major refactoring
  - When you want to optimize bootstrap performance

<info>Options:</info>
  --clear, -c        Clear existing cache before warming (recommended for fresh start)
  --attributes, -a   Pre-scan and cache #[ServiceMap] and #[Plugin] attributes for improved performance

<info>Examples:</info>
  # Warm cache with existing data
  <comment>phel cache:warm</comment>

  # Clear and warm cache from scratch
  <comment>phel cache:warm --clear</comment>

  # Warm cache with attribute pre-scanning (recommended for production)
  <comment>phel cache:warm --clear --attributes</comment>

To remove the compiled-code and temp caches instead, run <comment>phel cache:clear</comment>.
HELP);
    }
}
