<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;

/**
 * @internal
 */
final class InteropProvider extends AbstractProvider
{
    #[Provides(CommandFacadeInterface::class)]
    public function commandFacade(Container $container): CommandFacadeInterface
    {
        return $container->getLocator()->getRequired(CommandFacade::class);
    }

    #[Provides(BuildFacadeInterface::class)]
    public function buildFacade(Container $container): BuildFacadeInterface
    {
        return $container->getLocator()->getRequired(BuildFacade::class);
    }
}
