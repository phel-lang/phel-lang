<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;

final class BuildProvider extends AbstractProvider
{
    public const string FACADE_COMPILER = 'FACADE_COMPILER';

    public const string FACADE_COMMAND = 'FACADE_COMMAND';

    #[Provides(self::FACADE_COMPILER)]
    public function compilerFacade(Container $container): CompilerFacade
    {
        return $container->getLocator()->getRequired(CompilerFacade::class);
    }

    #[Provides(self::FACADE_COMMAND)]
    public function commandFacade(Container $container): CommandFacade
    {
        return $container->getLocator()->getRequired(CommandFacade::class);
    }
}
