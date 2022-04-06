<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\Command\CompileCommand;
use Phel\Formatter\FormatterFacade;
use Phel\Interop\InteropFacade;
use Phel\Run\RunFacade;

final class ConsoleDependencyProvider extends AbstractDependencyProvider
{
    public const COMMANDS = 'COMMANDS';

    public function provideModuleDependencies(Container $container): void
    {
        $interopFacade = new InteropFacade();
        $formatterFacade = new FormatterFacade();
        $runFacade = new RunFacade();

        $container->set(self::COMMANDS, static fn () => [
            $interopFacade->getExportCommand(),
            $formatterFacade->getFormatCommand(),
            $runFacade->getReplCommand(),
            $runFacade->getRunCommand(),
            $runFacade->getTestCommand(),
            new CompileCommand(),
        ]);
    }
}
