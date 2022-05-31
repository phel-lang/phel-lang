<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\Infrastructure\Command\CompileCommand;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use Phel\Interop\Infrastructure\Command\ExportCommand;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;

final class ConsoleDependencyProvider extends AbstractDependencyProvider
{
    public const COMMANDS = 'COMMANDS';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::COMMANDS, static fn () => [
            new ExportCommand(),
            new FormatCommand(),
            new ReplCommand(),
            new RunCommand(),
            new TestCommand(),
            new CompileCommand(),
        ]);
    }
}
