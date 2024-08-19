<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;

final class BuildProvider extends AbstractProvider
{
    public const FACADE_COMPILER = 'FACADE_COMPILER';

    public const FACADE_COMMAND = 'FACADE_COMMAND';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCompiler($container);
        $this->addFacadeCommand($container);
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
}
