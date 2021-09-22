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
        return $this->getFactory()
            ->createRuntimeLoader()
            ->loadRuntime();
    }

    /**
     * @internal for testing
     */
    public function addPath(string $namespacePrefix, array $path): void
    {
        $this->getRuntime()->addPath($namespacePrefix, $path);
    }
}
