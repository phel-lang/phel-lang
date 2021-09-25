<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Build\BuildFacade;
use Phel\Build\BuildFacadeInterface;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Config\ConfigFacade;
use Phel\Config\ConfigFacadeInterface;
use Phel\Formatter\FormatterFacade;
use Phel\Formatter\FormatterFacadeInterface;
use Phel\Interop\InteropFacade;
use Phel\Interop\InteropFacadeInterface;

final class CommandDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMPILER = 'FACADE_COMPILER';
    public const FACADE_FORMATTER = 'FACADE_FORMATTER';
    public const FACADE_INTEROP = 'FACADE_INTEROP';
    public const FACADE_CONFIG = 'FACADE_CONFIG';
    public const FACADE_BUILD = 'FACADE_BUILD';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCompiler($container);
        $this->addFacadeFormatter($container);
        $this->addFacadeInterop($container);
        $this->addFacadeConfig($container);
        $this->addFacadeBuild($container);
    }

    private function addFacadeCompiler(Container $container): void
    {
        $container->set(self::FACADE_COMPILER, function (Container $container): CompilerFacadeInterface {
            return $container->getLocator()->get(CompilerFacade::class);
        });
    }

    private function addFacadeFormatter(Container $container): void
    {
        $container->set(self::FACADE_FORMATTER, function (Container $container): FormatterFacadeInterface {
            return $container->getLocator()->get(FormatterFacade::class);
        });
    }

    private function addFacadeInterop(Container $container): void
    {
        $container->set(self::FACADE_INTEROP, function (Container $container): InteropFacadeInterface {
            return $container->getLocator()->get(InteropFacade::class);
        });
    }

    private function addFacadeConfig(Container $container): void
    {
        $container->set(self::FACADE_CONFIG, function (Container $container): ConfigFacadeInterface {
            return $container->getLocator()->get(ConfigFacade::class);
        });
    }

    private function addFacadeBuild(Container $container): void
    {
        $container->set(self::FACADE_BUILD, function (Container $container): BuildFacadeInterface {
            return $container->getLocator()->get(BuildFacade::class);
        });
    }
}
