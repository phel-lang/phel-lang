<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractFactory;
use Phel\Runtime\Exceptions\PhelRuntimeException;
use Phel\Runtime\Loader\ConfigLoader;
use Phel\Runtime\Loader\ConfigNormalizer;
use Phel\Runtime\Loader\RootPhelConfig;
use Phel\Runtime\Loader\VendorDir;

/**
 * @method RuntimeConfig getConfig()
 */
final class RuntimeFactory extends AbstractFactory
{
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

    public function createConfigLoader(): ConfigLoader
    {
        return new ConfigLoader(
            $this->createRootPhelConfig(),
            $this->createVendorDir(),
            $this->createConfigNormalizer()
        );
    }

    public function createVendorDir(): VendorDir
    {
        return new VendorDir(
            $this->getConfig()->getApplicationRootDir(),
            $this->createRootPhelConfig()
        );
    }

    private function createConfigNormalizer(): ConfigNormalizer
    {
        return new ConfigNormalizer();
    }

    private function createRootPhelConfig(): RootPhelConfig
    {
        return new RootPhelConfig(
            $this->getConfig()->getApplicationRootDir()
        );
    }
}
