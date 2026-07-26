<?php

declare(strict_types=1);

namespace Phel\Profile;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Run\RunFacade;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * @internal
 */
final class ProfileProvider extends AbstractProvider
{
    #[Provides(RunFacadeInterface::class)]
    public function runFacade(Container $container): RunFacadeInterface
    {
        return $container->getLocator()->getRequired(RunFacade::class);
    }
}
