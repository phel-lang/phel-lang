<?php

declare(strict_types=1);

namespace Phel\Api;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use Phel\Compiler\CompilerFacade;
use Phel\Run\RunFacade;

final class ApiProvider extends AbstractProvider
{
    public const string FACADE_RUN = 'FACADE_RUN';

    public const string FACADE_COMPILER = 'FACADE_COMPILER';

    #[Provides(self::FACADE_RUN)]
    public function runFacade(Container $container): RunFacade
    {
        return $container->getLocator()->getRequired(RunFacade::class);
    }

    #[Provides(self::FACADE_COMPILER)]
    public function compilerFacade(Container $container): CompilerFacade
    {
        return $container->getLocator()->getRequired(CompilerFacade::class);
    }
}
