<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Compiler\CompilerFacade;
use Phel\Run\RunFacade;
use Phel\Shared\Facade\CompilerFacadeInterface;
use Phel\Shared\Facade\RunFacadeInterface;

/**
 * @internal
 */
final class ApiProvider extends AbstractProvider
{
    #[Provides(RunFacadeInterface::class)]
    public function runFacade(Container $container): RunFacadeInterface
    {
        return $container->getLocator()->getRequired(RunFacade::class);
    }

    #[Provides(CompilerFacadeInterface::class)]
    public function compilerFacade(Container $container): CompilerFacadeInterface
    {
        return $container->getLocator()->getRequired(CompilerFacade::class);
    }
}
