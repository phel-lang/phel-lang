<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractFactory;
use Phel\Compiler\CompilerFacadeInterface;
use Phel\Runtime\Exceptions\PhelRuntimeException;
use Phel\Runtime\Extractor\NamespaceExtractor;
use Phel\Runtime\Extractor\NamespaceExtractorInterface;

/**
 * @method RuntimeConfig getConfig()
 */
final class RuntimeFactory extends AbstractFactory
{
    public function createNamespaceExtractor(): NamespaceExtractorInterface
    {
        return new NamespaceExtractor(
            $this->getConfig()->getApplicationRootDir(),
            $this->getCompilerFacade()
        );
    }

    /**
     * @throws PhelRuntimeException
     */
    public function getRuntime(): RuntimeInterface
    {
        if (RuntimeSingleton::isInitialized()) {
            return RuntimeSingleton::getInstance();
        }

        $runtimePath = $this->getConfig()->getApplicationRootDir()
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'PhelRuntime.php';

        if (!file_exists($runtimePath)) {
            throw PhelRuntimeException::couldNotBeLoadedFrom($runtimePath);
        }

        return require $runtimePath;
    }

    private function getCompilerFacade(): CompilerFacadeInterface
    {
        return $this->getProvidedDependency(RuntimeDependencyProvider::FACADE_COMPILER);
    }
}
