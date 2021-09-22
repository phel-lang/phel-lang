<?php

declare(strict_types=1);

namespace Phel\Runtime\Loader;

use Phel\Runtime\Exceptions\PhelRuntimeException;
use Phel\Runtime\RuntimeInterface;
use Phel\Runtime\RuntimeSingleton;

final class RuntimeLoader
{
    private ConfigLoader $configLoader;

    private VendorDir $vendorDir;

    public function __construct(
        ConfigLoader $configLoader,
        VendorDir $vendorDir
    ) {
        $this->configLoader = $configLoader;
        $this->vendorDir = $vendorDir;
    }

    /**
     * @throws PhelRuntimeException
     */
    public function loadRuntime(): RuntimeInterface
    {
        if (RuntimeSingleton::isInitialized()) {
            return RuntimeSingleton::getInstance();
        }

        $vendorDir = $this->vendorDir->getVendorDir();
        $rt = RuntimeSingleton::initialize();
        $config = $this->configLoader->loadConfig();

        foreach ($config as $ns => $paths) {
            $pathString = implode("', $vendorDir . '", $paths);
            $rt->addPath($ns, [$vendorDir . $pathString]);
        }

        return $rt;
    }
}
