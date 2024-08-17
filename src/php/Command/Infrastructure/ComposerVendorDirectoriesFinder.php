<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Gacela\Framework\DocBlockResolverAwareTrait;
use Phel\Command\CommandFacade;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;
use Phel\Config\PhelConfig;
use Phel\Phel;
use RuntimeException;

use function dirname;
use function sprintf;

/**
 * @method CommandFacade getFacade()
 */
final class ComposerVendorDirectoriesFinder implements VendorDirectoriesFinderInterface
{
    use DocBlockResolverAwareTrait;

    public function __construct(
        private readonly string $vendorDirectory,
    ) {
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
            try {
                $config = $this->getFacade()->readPhelConfig($phelConfigPath);
            } catch (RuntimeException) {
                $this->triggerNotice($phelConfigPath);
                continue;
            }

            $sourceDirectories = $config[PhelConfig::SRC_DIRS] ?? [];

            foreach ($sourceDirectories as $sourceDirectory) {
                $result[] = dirname($phelConfigPath) . '/' . $sourceDirectory;
            }
        }

        return $result;
    }

    public function triggerNotice(string $phelConfigPath): void
    {
        $message = sprintf(
            'The "%s" must return an array or a PhelConfig object. Path: %s',
            Phel::PHEL_CONFIG_FILE_NAME,
            $phelConfigPath,
        );

        trigger_error($message);
    }
}
