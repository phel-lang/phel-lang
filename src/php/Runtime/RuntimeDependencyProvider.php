<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;
use Phel\Compiler\CompilerFacade;

final class RuntimeDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMPILER = 'FACADE_COMPILER';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addCompilerFacade($container);
    }

    private function addCompilerFacade(Container $container): void
    {
        $container->set(self::FACADE_COMPILER, fn () => new CompilerFacade());
    }
}
