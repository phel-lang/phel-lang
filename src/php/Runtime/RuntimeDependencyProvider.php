<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\CompilerFacadeInterface;

final class RuntimeDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMPILER = 'FACADE_COMPILER';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addCompilerFacade($container);
    }

    private function addCompilerFacade(Container $container): void
    {
        $container->set(self::FACADE_COMPILER, function (Container $container): CompilerFacadeInterface {
            return $container->getLocator()->get(CompilerFacade::class);
        });
    }
}
