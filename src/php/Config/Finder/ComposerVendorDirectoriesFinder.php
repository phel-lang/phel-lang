<?php

declare(strict_types=1);

namespace Phel\Config\Finder;

use Phel\Config\ConfigConfig;

class ComposerVendorDirectoriesFinder implements VendorDirectoriesFinderInterface
{
    private const PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private string $vendorDirectory;

    public function __construct(string $vendorDirectory)
    {
        $this->vendorDirectory = $vendorDirectory;
    }

    public function findPhelSourceDirectories(): array
    {
        $vendorDir = $this->vendorDirectory;
        $pattern = $vendorDir . '/*/*/' . self::PHEL_CONFIG_FILE_NAME;

        $result = [];

        foreach (glob($pattern) as $phelConfigPath) {
            $relativeVendorConfigPath = substr($phelConfigPath, strlen($vendorDir) - strlen($phelConfigPath));
            $pathPrefix = dirname($relativeVendorConfigPath);
            /** @psalm-suppress UnresolvableInclude */
            $sourceDirectories = (require $phelConfigPath)[ConfigConfig::SRC_DIRS] ?? [];

            foreach ($sourceDirectories as $directory) {
                $result[] = $pathPrefix . '/' . $directory;
            }
        }

        return $result;
    }
}
