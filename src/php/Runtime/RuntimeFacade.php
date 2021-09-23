<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractFacade;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;

/**
 * @method RuntimeFactory getFactory()
 */
final class RuntimeFacade extends AbstractFacade implements RuntimeFacadeInterface
{
    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array
    {
        return $this->getRuntime()->getSourceDirectories();
    }

    public function getEnv(): GlobalEnvironmentInterface
    {
        return $this->getRuntime()->getEnv();
    }

    /**
     * @internal for testing
     */
    public function addPath(string $namespacePrefix, array $path): void
    {
        $this->getRuntime()->addPath($namespacePrefix, $path);
    }

    private function getRuntime(): RuntimeInterface
    {
        return $this->getFactory()
            ->createRuntimeLoader()
            ->loadRuntime();
    }
}
