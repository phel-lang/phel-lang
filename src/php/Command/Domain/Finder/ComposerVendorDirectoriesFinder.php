<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Finder;

use Phel\Command\CommandConfig;
use Phel\Config\PhelConfig;
use Phel\Config\PhelConfigException;
use Phel\Phel;

use function dirname;
use function is_array;

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
            $phelConfig = $this->parsePhelConfigAsArray($phelConfigPath);
            $sourceDirectories = $phelConfig[CommandConfig::SRC_DIRS] ?? [];

            foreach ($sourceDirectories as $directory) {
                $result[] = $pathPrefix . '/' . $directory;
            }
        }

        return $result;
    }

    private function parsePhelConfigAsArray(string $phelConfigPath): array
    {
        /**
         * @psalm-suppress UnresolvableInclude
         *
         * @var array|PhelConfig|mixed $phelConfig
         */
        $phelConfig = require $phelConfigPath;
        if ($phelConfig instanceof PhelConfig) {
            return $phelConfig->jsonSerialize();
        }

        if (!is_array($phelConfig)) {
            throw PhelConfigException::wrongType();
        }

        return $phelConfig;
    }
}
