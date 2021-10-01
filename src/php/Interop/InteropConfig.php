<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;

final class InteropConfig extends AbstractConfig
{
    public const EXPORT = 'export';

    public const EXPORT_NAMESPACE_PREFIX = 'namespace-prefix';
    public const EXPORT_TARGET_DIRECTORY = 'target-directory';
    public const EXPORT_DIRECTORIES = 'directories';

    public function prefixNamespace(): string
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
    public function getSourceDirectories(): array
    {
        return array_map(
            fn (string $dir): string => $this->getApplicationRootDir() . '/' . $dir,
            $this->get('src-dirs') ?? []
        );
    }

    /**
     * @return string[]
     */
    public function getExportDirectories(): array
    {
        return array_map(
            fn (string $dir): string => $this->getApplicationRootDir() . '/' . $dir,
            $this->get('export')[self::EXPORT_DIRECTORIES] ?? []
        );
    }

    public function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }
}
