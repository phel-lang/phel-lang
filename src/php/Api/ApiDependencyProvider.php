<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Run\RunFacade;

final class ApiDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_RUN = 'FACADE_RUN';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addRunFacade($container);
    }

    private function addRunFacade(Container $container): void
    {
        $container->set(self::FACADE_RUN, static function (Container $container) {
            return $container->getLocator()->get(RunFacade::class);
        });
    }
}
