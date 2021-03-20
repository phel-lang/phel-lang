<?php

declare(strict_types=1);

namespace Phel\Main;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;
use Phel\Command\CommandFacade;

final class MainDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMMAND = 'FACADE_COMMAND';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addCommandFacade($container);
    }

    private function addCommandFacade(Container $container): void
    {
        $container->set(self::FACADE_COMMAND, fn () => new CommandFacade());
    }
}
