<?php

declare(strict_types=1);

namespace Phel\Command\Finder;

use Phel\Command\CommandConfig;

final class ComposerVendorDirectoriesFinder implements VendorDirectoriesFinderInterface
{
    private const PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private string $vendorDirectory;

    public function __construct(string $vendorDirectory)
    {
        $this->vendorDirectory = $vendorDirectory;
    }

    /**
     * @return list<string>
     */
    public function findPhelSourceDirectories(): array
    {
        $vendorDir = $this->vendorDirectory;
        $pattern = $vendorDir . '/*/*/' . self::PHEL_CONFIG_FILE_NAME;

        $result = [];

        foreach (glob($pattern) as $phelConfigPath) {
            $pathPrefix = dirname($phelConfigPath);
            /** @psalm-suppress UnresolvableInclude */
            $sourceDirectories = (require $phelConfigPath)[CommandConfig::SRC_DIRS] ?? [];

            foreach ($sourceDirectories as $directory) {
                $result[] = $pathPrefix . '/' . $directory;
            }
        }

        return $result;
    }
}
