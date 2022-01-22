<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\BuildFacade;
use Phel\Command\CommandFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Formatter\FormatterFacade;
use Phel\Interop\InteropFacade;

final class RunDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMMAND = 'FACADE_COMMAND';
    public const FACADE_COMPILER = 'FACADE_COMPILER';
    public const FACADE_FORMATTER = 'FACADE_FORMATTER';
    public const FACADE_INTEROP = 'FACADE_INTEROP';
    public const FACADE_BUILD = 'FACADE_BUILD';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCommand($container);
        $this->addFacadeCompiler($container);
        $this->addFacadeFormatter($container);
        $this->addFacadeInterop($container);
        $this->addFacadeBuild($container);
    }

    private function addFacadeCommand(Container $container): void
    {
        $container->set(self::FACADE_COMMAND, function (Container $container) {
            return $container->getLocator()->get(CommandFacade::class);
        });
    }

    private function addFacadeCompiler(Container $container): void
    {
        $container->set(self::FACADE_COMPILER, function (Container $container) {
            return $container->getLocator()->get(CompilerFacade::class);
        });
    }

    private function addFacadeFormatter(Container $container): void
    {
        $container->set(self::FACADE_FORMATTER, function (Container $container) {
            return $container->getLocator()->get(FormatterFacade::class);
        });
    }

    private function addFacadeInterop(Container $container): void
    {
        $container->set(self::FACADE_INTEROP, function (Container $container) {
            return $container->getLocator()->get(InteropFacade::class);
        });
    }

    private function addFacadeBuild(Container $container): void
    {
        $container->set(self::FACADE_BUILD, function (Container $container) {
            return $container->getLocator()->get(BuildFacade::class);
        });
    }
}
