<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\NamespaceExtractor\NamespaceExtractorFacade;
use Phel\NamespaceExtractor\NamespaceExtractorFacadeInterface;
use Phel\Runtime\RuntimeFacade;
use Phel\Runtime\RuntimeFacadeInterface;

final class InteropDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_RUNTIME = 'FACADE_RUNTIME';
    public const FACADE_NAMESPACE_EXTRACTOR = 'FACADE_NAMESPACE_EXTRACTOR';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeRuntime($container);
        $this->addFacadeNamespaceExtractor($container);
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
