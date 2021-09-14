<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFactory;
use Phel\Build\Extractor\NamespaceExtractor;
use Phel\Build\Extractor\NamespaceSorterInterface;
use Phel\Build\Extractor\TopologicalNamespaceSorter;
use Phel\Compiler\CompilerFacadeInterface;

final class BuildFactory extends AbstractFactory
{
    public function createNamespaceExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            $this->getCompilerFacade(),
            $this->createNamespaceSorter()
        );
    }

    public function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(BuildDependencyProvider::FACADE_COMPILER);
    }

    private function createNamespaceSorter(): NamespaceSorterInterface
    {
        return new TopologicalNamespaceSorter();
    }
}
