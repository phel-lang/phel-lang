<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;

final class InteropConfig extends AbstractConfig
{
    public const EXPORT = 'export';

    private const EXPORT_DIRECTORIES = 'directories';
    private const EXPORT_NAMESPACE_PREFIX = 'namespace-prefix';
    private const EXPORT_TARGET_DIRECTORY = 'target-directory';

    private const DEFAULT_EXPORT_DIRECTORIES = ['src'];
    private const DEFAULT_EXPORT_NAMESPACE_PREFIX = 'PhelGenerated';
    private const DEFAULT_EXPORT_TARGET_DIRECTORY = 'src/PhelGenerated';

    private const DEFAULT_EXPORT = [
        self::EXPORT_DIRECTORIES => self::DEFAULT_EXPORT_DIRECTORIES,
        self::EXPORT_NAMESPACE_PREFIX => self::DEFAULT_EXPORT_NAMESPACE_PREFIX,
        self::EXPORT_TARGET_DIRECTORY => self::DEFAULT_EXPORT_TARGET_DIRECTORY,
    ];

    public function prefixNamespace(): string
    {
        return (string)($this->getExport()[self::EXPORT_NAMESPACE_PREFIX] ?? self::DEFAULT_EXPORT_NAMESPACE_PREFIX);
    }

    public function getExportTargetDirectory(): string
    {
        return (string)($this->getExport()[self::EXPORT_TARGET_DIRECTORY] ?? self::DEFAULT_EXPORT_TARGET_DIRECTORY);
    }

    /**
     * @return list<string>
     */
    public function getExportDirectories(): array
    {
        return array_map(
            fn (string $dir): string => $this->getApplicationRootDir() . '/' . $dir,
            $this->getExport()[self::EXPORT_DIRECTORIES] ?? self::DEFAULT_EXPORT_DIRECTORIES
        );
    }

    public function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }

    private function getExport(): array
    {
        return $this->get(self::EXPORT, self::DEFAULT_EXPORT);
    }
}
