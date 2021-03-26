<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;
use Phel\Runtime\RuntimeFacade;
use Phel\Runtime\RuntimeFacadeInterface;

final class InteropDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_RUNTIME = 'FACADE_RUNTIME';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeRuntime($container);
    }

    private function addFacadeRuntime(Container $container): void
    {
        $container->set(self::FACADE_RUNTIME, function (Container $container): RuntimeFacadeInterface {
            return $container->getLocator()->get(RuntimeFacade::class);
        });
    }
}
