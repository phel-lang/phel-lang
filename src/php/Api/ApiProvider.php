<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Phel\Run\RunFacade;

final class ApiProvider extends AbstractProvider
{
    public const FACADE_RUN = 'FACADE_RUN';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addRunFacade($container);
    }

    private function addRunFacade(Container $container): void
    {
        $container->set(
            self::FACADE_RUN,
            static fn (Container $container) => $container->getLocator()->get(RunFacade::class),
        );
    }
}
