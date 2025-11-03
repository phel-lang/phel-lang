<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Filesystem\FilesystemFacade;

final class BuildProvider extends AbstractProvider
{
    public const string FACADE_COMPILER = 'FACADE_COMPILER';

    public const string FACADE_COMMAND = 'FACADE_COMMAND';

    public const string FACADE_FILESYSTEM = 'FACADE_FILESYSTEM';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCompiler($container);
        $this->addFacadeCommand($container);
        $this->addFacadeFilesystem($container);
    }

    private function addFacadeCompiler(Container $container): void
    {
        $container->set(
            self::FACADE_COMPILER,
            static fn (Container $container) => $container->getLocator()->get(CompilerFacade::class),
        );
    }

    private function addFacadeCommand(Container $container): void
    {
        $container->set(
            self::FACADE_COMMAND,
            static fn (Container $container) => $container->getLocator()->get(CommandFacade::class),
        );
    }

    private function addFacadeFilesystem(Container $container): void
    {
        $container->set(
            self::FACADE_FILESYSTEM,
            static fn (Container $container) => $container->getLocator()->get(FilesystemFacade::class),
        );
    }
}
