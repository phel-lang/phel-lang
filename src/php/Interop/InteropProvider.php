<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;

final class InteropProvider extends AbstractProvider
{
    public const FACADE_COMMAND = 'FACADE_COMMAND';

    public const FACADE_BUILD = 'FACADE_BUILD';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCommand($container);
        $this->addFacadeBuild($container);
    }

    private function addFacadeCommand(Container $container): void
    {
        $container->set(
            self::FACADE_COMMAND,
            static fn (Container $container) => $container->getLocator()->get(CommandFacade::class),
        );
    }

    private function addFacadeBuild(Container $container): void
    {
        $container->set(
            self::FACADE_BUILD,
            static fn (Container $container) => $container->getLocator()->get(BuildFacade::class),
        );
    }
}
