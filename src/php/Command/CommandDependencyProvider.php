<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\AbstractDependencyProvider;
use Gacela\Container\Container;
use Phel\Compiler\CompilerFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Interop\InteropFacade;
use Phel\Runtime\RuntimeFacade;

final class CommandDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMPILER = 'FACADE_COMPILER';
    public const FACADE_FORMATTER = 'FACADE_FORMATTER';
    public const FACADE_INTEROP = 'FACADE_INTEROP';
    public const FACADE_RUNTIME = 'FACADE_RUNTIME';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCompiler($container);
        $this->addFacadeFormatter($container);
        $this->addFacadeInterop($container);
        $this->addFacadeRuntime($container);
    }

    private function addFacadeCompiler(Container $container): void
    {
        $container->set(self::FACADE_COMPILER, fn () => new CompilerFacade());
    }

    private function addFacadeFormatter(Container $container): void
    {
        $container->set(self::FACADE_FORMATTER, fn () => new FormatterFacade());
    }

    private function addFacadeInterop(Container $container): void
    {
        $container->set(self::FACADE_INTEROP, fn () => new InteropFacade());
    }

    private function addFacadeRuntime(Container $container): void
    {
        $container->set(self::FACADE_RUNTIME, fn () => new RuntimeFacade());
    }
}
