<?php

declare(strict_types=1);

namespace Phel\Command\Infrastructure;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Phel;
use Phel\Command\CommandFacade;
use Phel\Command\Domain\Finder\VendorDirectoriesFinderInterface;
use Phel\Config\PhelConfig;
use Phel\Shared\ScalarCoercion;
use RuntimeException;

use function dirname;
use function glob;
use function sprintf;

/**
 * @method CommandFacade getFacade()
 *
 * @internal
 */
#[ServiceMap(method: 'getFacade', className: CommandFacade::class)]
final class ComposerVendorDirectoriesFinder implements VendorDirectoriesFinderInterface
{
    use ServiceResolverAwareTrait;

    private const string PHEL_PACKAGE = 'phel-lang/phel-lang';

    public function __construct(
        private readonly string $vendorDirectory,
    ) {}

    /**
     * @return list<string>
     */
    public function findPhelSourceDirectories(): array
    {
        $vendorDir = $this->vendorDirectory;
        $pattern = $vendorDir . '/*/*/' . Phel::PHEL_CONFIG_FILE_NAME;

        $result = [];

        $configPaths = glob($pattern) ?: [];

        foreach ($configPaths as $phelConfigPath) {
            // Phel's own package: under Composer it is the running Phel, already
            // scanned as the internal source dir; under a PHAR it is a second
            // copy whose stdlib would override the bundled one. `phel doctor`
            // reports it; a warning here would repeat on every command.
            if ($phelConfigPath === $vendorDir . '/' . self::PHEL_PACKAGE . '/' . Phel::PHEL_CONFIG_FILE_NAME) {
                continue;
            }

            try {
                $config = $this->getFacade()->readPhelConfig($phelConfigPath);
            } catch (RuntimeException) {
                $this->triggerNotice($phelConfigPath);
                continue;
            }

            $sourceDirectories = ScalarCoercion::toStringList($config[PhelConfig::SRC_DIRS] ?? null);

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
