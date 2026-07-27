<?php

declare(strict_types=1);

namespace Phel\Nrepl;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Api\ApiFacade;
use Phel\Run\RunFacade;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * @internal
 */
final class NreplProvider extends AbstractProvider
{
    #[Provides(RunFacadeInterface::class)]
    public function runFacade(Container $container): RunFacadeInterface
    {
        return $container->getLocator()->getRequired(RunFacade::class);
    }

    #[Provides(ApiFacadeInterface::class)]
    public function apiFacade(Container $container): ApiFacadeInterface
    {
        return $container->getLocator()->getRequired(ApiFacade::class);
    }
}
