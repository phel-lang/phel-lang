<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\BuildFacade;
use Phel\Build\BuildFacadeInterface;
use Phel\Config\ConfigFacade;
use Phel\Config\ConfigFacadeInterface;

final class InteropDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_CONFIG = 'FACADE_CONFIG';
    public const FACADE_BUILD = 'FACADE_BUILD';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeConfig($container);
        $this->addFacadeBuild($container);
    }

    private function addFacadeConfig(Container $container): void
    {
        $container->set(self::FACADE_CONFIG, function (Container $container): ConfigFacadeInterface {
            return $container->getLocator()->get(ConfigFacade::class);
        });
    }

    private function addFacadeBuild(Container $container): void
    {
        $container->set(self::FACADE_BUILD, function (Container $container): BuildFacadeInterface {
            return $container->getLocator()->get(BuildFacade::class);
        });
    }
}
