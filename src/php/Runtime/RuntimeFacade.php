<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractFacade;

/**
 * @method RuntimeFactory getFactory()
 */
final class RuntimeFacade extends AbstractFacade implements RuntimeFacadeInterface
{
    public function getRuntime(): RuntimeInterface
    {
        return $this->getFactory()->getRuntime();
    }

    public function getNamespacesFromDirectories(array $directories): array
    {
        return $this->getFactory()
            ->createNamespaceExtractor()
            ->getNamespacesFromDirectories($directories);
    }

    public function getNamespaceFromFile(string $path): string
    {
        return $this
            ->getFactory()
            ->createNamespaceExtractor()
            ->getNamespaceFromFile($path);
    }

    public function addPath(string $namespacePrefix, array $path): void
    {
        $this->getRuntime()->addPath($namespacePrefix, $path);
    }
}
