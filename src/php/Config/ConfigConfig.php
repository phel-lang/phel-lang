<?php

declare(strict_types=1);

namespace Phel\Config;

use Gacela\Framework\Config;
use Phel\AbstractPhelConfig;

final class ConfigConfig extends AbstractPhelConfig
{
    public const EXPORT = 'export';
    public const SRC_DIRS = 'src_dirs';
    public const TEST_DIRS = 'test_dirs';
    public const VENDOR_DIR = 'vendor_dir';

    public const EXPORT_NAMESPACE_PREFIX = 'namespace-prefix';
    public const EXPORT_TARGET_DIRECTORY = 'target-directory';
    public const EXPORT_DIRECTORIES = 'directories';

    public function getSourceDirectories(): array
    {
        return $this->toAbsoluteDirectories((array)$this->get(self::SRC_DIRS));
    }

    public function getTestDirectories(): array
    {
        return $this->toAbsoluteDirectories((array)$this->get(self::TEST_DIRS));
    }

    public function getVendorDir(): string
    {
        return $this->getApplicationRootDir() . '/' . ((string)$this->get(self::VENDOR_DIR));
    }

    public function getExportNamespacePrefix(): string
    {
        return (string)$this->get(self::EXPORT)[self::EXPORT_NAMESPACE_PREFIX];
    }

    public function getExportTargetDirectory(): string
    {
        return (string)($this->get(self::EXPORT)[self::EXPORT_TARGET_DIRECTORY] ?? 'PhelGenerated');
    }

    /**
     * @return string[]
     */
    public function getExportDirectories(): array
    {
        return $this->toAbsoluteDirectories($this->get(self::EXPORT)[self::EXPORT_DIRECTORIES] ?? []);
    }

    public function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }

    private function toAbsoluteDirectories(array $relativeDirectories): array
    {
        return array_map(
            fn (string $dir): string => $this->getApplicationRootDir() . '/' . $dir,
            $relativeDirectories
        );
    }
}
