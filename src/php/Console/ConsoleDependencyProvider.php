<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\Command\CompileCommand;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use Phel\Interop\InteropFacade;
use Phel\Run\Command\TestCommand;
use Phel\Run\RunFacade;

final class ConsoleDependencyProvider extends AbstractDependencyProvider
{
    public const COMMANDS = 'COMMANDS';

    public function provideModuleDependencies(Container $container): void
    {
        $interopFacade = new InteropFacade();
        $runFacade = new RunFacade();

        $container->set(self::COMMANDS, static fn () => [
            $interopFacade->getExportCommand(),
            new FormatCommand(),
            $runFacade->getReplCommand(),
            $runFacade->getRunCommand(),
            new TestCommand(),
            new CompileCommand(),
        ]);
    }
}
