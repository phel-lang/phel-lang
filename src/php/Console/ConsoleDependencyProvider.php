<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\Infrastructure\Command\CompileCommand;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use Phel\Interop\Infrastructure\Command\ExportCommand;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;
use Phel\Run\RunFacade;

final class ConsoleDependencyProvider extends AbstractDependencyProvider
{
    public const COMMANDS = 'COMMANDS';

    public function provideModuleDependencies(Container $container): void
    {
        $runFacade = new RunFacade();

        $container->set(self::COMMANDS, static fn () => [
            new ExportCommand(),
            new FormatCommand(),
            $runFacade->getReplCommand(),
            new RunCommand(),
            new TestCommand(),
            new CompileCommand(),
        ]);
    }
}
