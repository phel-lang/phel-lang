<?php

declare(strict_types=1);

namespace Phel\Config;

use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Event\ConfigReader\ReadPhpConfigEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Phel\Build\BuildConfig;
use Phel\Command\CommandConfig;
use Phel\Filesystem\FilesystemConfig;
use Phel\Formatter\FormatterConfig;
use Phel\Interop\InteropConfig;
use RuntimeException;

final class ConfigReader implements ConfigReaderInterface
{
    use EventDispatchingCapabilities;

    public function read(string $absolutePath): array
    {
        if (!$this->canRead($absolutePath)) {
            return [];
        }

        self::dispatchEvent(new ReadPhpConfigEvent($absolutePath));

        /**
         * @psalm-suppress UnresolvableInclude
         */
        $content = include $absolutePath;

        if (!$content instanceof PhelConfig) {
            throw new RuntimeException(sprintf('The config must return an instance of %s.', PhelConfig::class));
        }

        return [
            CommandConfig::SRC_DIRS => $content->getSrcDirs(),
            CommandConfig::TEST_DIRS => $content->getTestDirs(),
            CommandConfig::VENDOR_DIR => $content->getVendorDir(),
            CommandConfig::OUTPUT_DIR => $content->getOutDir(),
            InteropConfig::EXPORT => [
                InteropConfig::EXPORT_TARGET_DIRECTORY => $content->getExport()->getTargetDirectory(),
                InteropConfig::EXPORT_DIRECTORIES => $content->getExport()->getDirectories(),
                InteropConfig::EXPORT_NAMESPACE_PREFIX => $content->getExport()->getNamespacePrefix(),
            ],
            BuildConfig::IGNORE_WHEN_BUILDING => $content->getIgnoreWhenBuilding(),
            FilesystemConfig::KEEP_GENERATED_TEMP_FILES => $content->isKeepGeneratedTempFiles(),
            FormatterConfig::FORMAT_DIRS => $content->getFormatDirs(),
        ];
    }

    private function canRead(string $absolutePath): bool
    {
        $extension = pathinfo($absolutePath, PATHINFO_EXTENSION);

        return $extension === 'php' && file_exists($absolutePath);
    }
}
