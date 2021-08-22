<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;

final class InteropConfig extends AbstractConfig
{
    public const EXPORT_DIRECTORIES = 'InteropConfig::EXPORT_DIRECTORIES';
    public const EXPORT_NAMESPACE_PREFIX = 'InteropConfig::EXPORT_NAMESPACE_PREFIX';
    public const EXPORT_TARGET_DIRECTORY = 'InteropConfig::EXPORT_TARGET_DIRECTORY';

    public function prefixNamespace(): string
    {
        return (string)$this->get(InteropConfig::EXPORT_NAMESPACE_PREFIX);
    }

    public function getExportTargetDirectory(): string
    {
        return (string)$this->get(InteropConfig::EXPORT_TARGET_DIRECTORY, 'PhelGenerated');
    }

    /**
     * @return string[]
     */
    public function getExportDirectories(): array
    {
        return array_map(
            fn (string $dir): string => $this->getApplicationRootDir() . '/' . $dir,
            $this->get(InteropConfig::EXPORT_DIRECTORIES, [])
        );
    }

    public function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }
}
