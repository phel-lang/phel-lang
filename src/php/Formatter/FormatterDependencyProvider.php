<?php

declare(strict_types=1);

namespace Phel\Formatter;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;
use Phel\Compiler\CompilerFacade;

final class FormatterDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMPILER = 'FACADE_COMPILER';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCompiler($container);
    }

    private function addFacadeCompiler(Container $container): void
    {
        $container->set(self::FACADE_COMPILER, fn () => new CompilerFacade());
    }
}
