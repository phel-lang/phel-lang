<?php

declare(strict_types=1);

namespace Phel\Watch;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Api\ApiFacade;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Run\RunFacade;
use Phel\Shared\Facade\ApiFacadeInterface;
use Phel\Shared\Facade\BuildFacadeInterface;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * Cross-module dependencies are keyed by the interface the consumer asks for,
 * not by a string constant. Gacela types `getProvidedDependency(X::class)` as
 * `X`, so the factory needs no `@var` and there is no constant to keep in sync
 * with its single call site.
 *
 * @internal
 */
final class WatchProvider extends AbstractProvider
{
    #[Provides(RunFacadeInterface::class)]
    public function runFacade(Container $container): RunFacadeInterface
    {
        return $container->getLocator()->getRequired(RunFacade::class);
    }

    #[Provides(BuildFacadeInterface::class)]
    public function buildFacade(Container $container): BuildFacadeInterface
    {
        return $container->getLocator()->getRequired(BuildFacade::class);
    }

    #[Provides(ApiFacadeInterface::class)]
    public function apiFacade(Container $container): ApiFacadeInterface
    {
        return $container->getLocator()->getRequired(ApiFacade::class);
    }

    #[Provides(CommandFacadeInterface::class)]
    public function commandFacade(Container $container): CommandFacadeInterface
    {
        return $container->getLocator()->getRequired(CommandFacade::class);
    }
}
