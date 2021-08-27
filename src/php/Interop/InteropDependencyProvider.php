<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\BuildFacade;
use Phel\Build\BuildFacadeInterface;
use Phel\Runtime\RuntimeFacade;
use Phel\Runtime\RuntimeFacadeInterface;

final class InteropDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_RUNTIME = 'FACADE_RUNTIME';
    public const FACADE_BUILD = 'FACADE_BUILD';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeRuntime($container);
        $this->addFacadeBuild($container);
    }

    private function addFacadeRuntime(Container $container): void
    {
        $container->set(self::FACADE_RUNTIME, function (Container $container): RuntimeFacadeInterface {
            return $container->getLocator()->get(RuntimeFacade::class);
        });
    }

    private function addFacadeBuild(Container $container): void
    {
        $container->set(self::FACADE_BUILD, function (Container $container): BuildFacadeInterface {
            return $container->getLocator()->get(BuildFacade::class);
        });
    }
}
