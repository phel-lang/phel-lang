<?php

declare(strict_types=1);

namespace Phel\Balance;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Shared\Facade\CommandFacadeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;

/**
 * @internal
 */
#[ServiceMap(method: 'getConfig', className: BalanceConfig::class)]
final class BalanceProvider extends AbstractProvider
{
    #[Provides(CompilerFacadeInterface::class)]
    public function compilerFacade(Container $container): CompilerFacadeInterface
    {
        return $container->getLocator()->getRequired(CompilerFacade::class);
    }

    #[Provides(CommandFacadeInterface::class)]
    public function commandFacade(Container $container): CommandFacadeInterface
    {
        return $container->getLocator()->getRequired(CommandFacade::class);
    }
}
