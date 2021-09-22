<?php

declare(strict_types=1);

namespace Phel\Runtime\Loader;

final class VendorDir
{
    public const DEFAULT_VENDOR_DIR = 'vendor';

    private string $applicationRootDir;

    private RootPhelConfig $rootPhelConfig;

    public function __construct(
        string $applicationRootDir,
        RootPhelConfig $rootPhelConfig
    ) {
        $this->rootPhelConfig = $rootPhelConfig;
        $this->applicationRootDir = $applicationRootDir;
    }

    public function getVendorDir(): string
    {
        $vendorDir = $this->rootPhelConfig->getRootPhelConfig()['vendor-dir'] ?? self::DEFAULT_VENDOR_DIR;

        return $this->applicationRootDir . $vendorDir;
    }
}
