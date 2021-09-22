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

    public function addPath(string $namespacePrefix, array $path): void
    {
        $this->getRuntime()->addPath($namespacePrefix, $path);
    }

    /**
     * @return array<string, list<string>> [ns => [path1, path2, ...]]
     */
    public function loadConfig(): array
    {
        return $this->getFactory()
            ->createConfigLoader()
            ->loadConfig();
    }

    public function getVendorDir(): string
    {
        return $this->getFactory()
            ->createVendorDir()
            ->getVendorDir();
    }
}
