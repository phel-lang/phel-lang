<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Formatter\FormatterFacade;
use Phel\Formatter\FormatterFacadeInterface;
use Phel\Interop\InteropFacade;
use Phel\Interop\InteropFacadeInterface;
use Phel\NamespaceExtractor\NamespaceExtractorFacade;
use Phel\NamespaceExtractor\NamespaceExtractorFacadeInterface;
use Phel\Runtime\RuntimeFacade;
use Phel\Runtime\RuntimeFacadeInterface;

final class CommandDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMPILER = 'FACADE_COMPILER';
    public const FACADE_FORMATTER = 'FACADE_FORMATTER';
    public const FACADE_INTEROP = 'FACADE_INTEROP';
    public const FACADE_RUNTIME = 'FACADE_RUNTIME';
    public const FACADE_NAMESPACE_EXTRACTOR = 'FACADE_NAMESPACE_EXTRACTOR';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeCompiler($container);
        $this->addFacadeFormatter($container);
        $this->addFacadeInterop($container);
        $this->addFacadeRuntime($container);
        $this->addFacadeNamespaceExtractor($container);
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

    private function addFacadeRuntime(Container $container): void
    {
        $container->set(self::FACADE_RUNTIME, function (Container $container): RuntimeFacadeInterface {
            return $container->getLocator()->get(RuntimeFacade::class);
        });
    }

    private function addFacadeNamespaceExtractor(Container $container): void
    {
        $container->set(self::FACADE_NAMESPACE_EXTRACTOR, function (Container $container): NamespaceExtractorFacadeInterface {
            return $container->getLocator()->get(NamespaceExtractorFacade::class);
        });
    }
}
