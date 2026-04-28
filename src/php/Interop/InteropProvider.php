<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;

final class InteropProvider extends AbstractProvider
{
    public const string FACADE_COMMAND = 'FACADE_COMMAND';

    public const string FACADE_BUILD = 'FACADE_BUILD';

    #[Provides(self::FACADE_COMMAND)]
    public function commandFacade(Container $container): CommandFacade
    {
        return $container->getLocator()->getRequired(CommandFacade::class);
    }

    #[Provides(self::FACADE_BUILD)]
    public function buildFacade(Container $container): BuildFacade
    {
        return $container->getLocator()->getRequired(BuildFacade::class);
    }
}
