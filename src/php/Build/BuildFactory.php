<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractFactory;
use Phel\Build\Extractor\NamespaceExtractor;
use Phel\Compiler\CompilerFacadeInterface;

final class BuildFactory extends AbstractFactory
{
    public function createNamespaceExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            $this->getCompilerFacade()
        );
    }

    private function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(BuildDependencyProvider::FACADE_COMPILER);
    }
}
