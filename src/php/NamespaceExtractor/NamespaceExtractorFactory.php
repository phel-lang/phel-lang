<?php

declare(strict_types=1);

namespace Phel\NamespaceExtractor;

use Gacela\Framework\AbstractFactory;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\NamespaceExtractor\Extractor\NamespaceExtractor;

final class NamespaceExtractorFactory extends AbstractFactory
{
    public function createNamespaceExtractor(): NamespaceExtractor
    {
        return new NamespaceExtractor(
            $this->getCompilerFacade()
        );
    }

    private function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(NamespaceExtractorDependencyProvider::FACADE_COMPILER);
    }
}
