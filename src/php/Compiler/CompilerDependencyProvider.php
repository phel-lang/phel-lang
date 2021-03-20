<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;
use Phel\Runtime\RuntimeFacade;

final class CompilerDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_RUNTIME = 'FACADE_RUNTIME';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeRuntime($container);
    }

    private function addFacadeRuntime(Container $container): void
    {
        $container->set(self::FACADE_RUNTIME, fn () => new RuntimeFacade());
    }
}
