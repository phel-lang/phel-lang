<?php

declare(strict_types=1);

namespace Phel\NamespaceExtractor;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\CompilerFacadeInterface;

final class NamespaceExtractorDependencyProvider extends AbstractDependencyProvider
{
    public const FACADE_COMPILER = 'FACADE_COMPILER';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addFacadeRuntime($container);
    }

    private function addFacadeRuntime(Container $container): void
    {
        $container->set(self::FACADE_COMPILER, function (Container $container): CompilerFacadeInterface {
            return $container->getLocator()->get(CompilerFacade::class);
        });
    }
}
