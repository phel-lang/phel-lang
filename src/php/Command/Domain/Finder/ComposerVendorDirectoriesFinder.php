<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Finder;

use Phel\Command\CommandConfig;

use Phel\Phel;

use function dirname;

final class ComposerVendorDirectoriesFinder implements VendorDirectoriesFinderInterface
{
    public function __construct(private string $vendorDirectory)
    {
    }

    /**
     * @return list<string>
     */
    public function findPhelSourceDirectories(): array
    {
        $vendorDir = $this->vendorDirectory;
        $pattern = $vendorDir . '/*/*/' . Phel::PHEL_CONFIG_FILE_NAME;

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
