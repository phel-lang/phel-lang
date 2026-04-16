<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Run\RunFacade;

final class ApiProvider extends AbstractProvider
{
    public const string FACADE_RUN = 'FACADE_RUN';

    #[Provides(self::FACADE_RUN)]
    public function runFacade(Container $container): RunFacade
    {
        return $container->getLocator()->getRequired(RunFacade::class);
    }
}
