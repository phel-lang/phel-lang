<?php

declare(strict_types=1);

namespace Phel\Console;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Api\Infrastructure\Command\DocCommand;
use Phel\Build\Infrastructure\Command\BuildCommand;
use Phel\Filesystem\FilesystemFacade;
use Phel\Formatter\Infrastructure\Command\FormatCommand;
use Phel\Interop\Infrastructure\Command\ExportCommand;
use Phel\Run\Infrastructure\Command\ReplCommand;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;

final class ConsoleProvider extends AbstractProvider
{
    public const COMMANDS = 'COMMANDS';

    public const FACADE_FILESYSTEM = 'FACADE_FILESYSTEM';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFilesystemFacade($container);
        $this->addCommands($container);
    }

    private function addFilesystemFacade(Container $container): void
    {
        $container->set(
            self::FACADE_FILESYSTEM,
            static fn (Container $container) => $container->getLocator()->get(FilesystemFacade::class),
        );
    }

    private function addCommands(Container $container): void
    {
        $container->set(self::COMMANDS, static fn (): array => [
            new ExportCommand(),
            new FormatCommand(),
            new ReplCommand(),
            new RunCommand(),
            new TestCommand(),
            new DocCommand(),
            new BuildCommand(),
        ]);
    }
}
