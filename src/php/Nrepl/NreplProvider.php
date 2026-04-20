<?php

declare(strict_types=1);

namespace Phel\Nrepl;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Api\ApiFacade;
use Phel\Run\RunFacade;

final class NreplProvider extends AbstractProvider
{
    public const string FACADE_RUN = 'FACADE_RUN';

    public const string FACADE_API = 'FACADE_API';

    #[Provides(self::FACADE_RUN)]
    public function runFacade(Container $container): RunFacade
    {
        return $container->getLocator()->getRequired(RunFacade::class);
    }

    #[Provides(self::FACADE_API)]
    public function apiFacade(Container $container): ApiFacade
    {
        return $container->getLocator()->getRequired(ApiFacade::class);
    }
}
