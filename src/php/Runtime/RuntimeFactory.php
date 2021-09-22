<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Gacela\Framework\AbstractFactory;
use Phel\Runtime\Loader\ConfigLoader;
use Phel\Runtime\Loader\ConfigNormalizer;
use Phel\Runtime\Loader\RootPhelConfig;
use Phel\Runtime\Loader\RuntimeLoader;
use Phel\Runtime\Loader\VendorDir;

/**
 * @method RuntimeConfig getConfig()
 */
final class RuntimeFactory extends AbstractFactory
{
    public function createRuntimeLoader(): RuntimeLoader
    {
        return new RuntimeLoader(
            $this->createVendorDir(),
            $this->createConfigLoader()
        );
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
