<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Command\CommandFacade;
use Phel\Transpiler\TranspilerFacade;

final class BuildDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_TRANSPILER = 'FACADE_TRANSPILER';

    public const FACADE_COMMAND = 'FACADE_COMMAND';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeTranspiler($container);
        $this->addFacadeCommand($container);
    }

    private function addFacadeTranspiler(Container $container): void
    {
        $container->set(
            self::FACADE_TRANSPILER,
            static fn (Container $container) => $container->getLocator()->get(TranspilerFacade::class),
        );
    }

    private function addFacadeCommand(Container $container): void
    {
        $container->set(
            self::FACADE_COMMAND,
            static fn (Container $container) => $container->getLocator()->get(CommandFacade::class),
        );
    }
}
