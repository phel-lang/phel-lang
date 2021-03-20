<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\AbstractConfig;

final class InteropConfig extends AbstractConfig
{
    public const EXPORT_NAMESPACE_PREFIX = 'EXPORT_NAMESPACE_PREFIX';
    public const EXPORT_DIRECTORIES = 'EXPORT_DIRECTORIES';
    public const EXPORT_TARGET_DIRECTORY = 'EXPORT_TARGET_DIRECTORY';

    public function prefixNamespace(): string
    {
        return (string)$this->get(self::EXPORT_NAMESPACE_PREFIX, '');
    }

    public function getExportTargetDirectory(): string
    {
        return $this->get(self::EXPORT_TARGET_DIRECTORY, 'PhelGenerated');
    }

    public function getExportDirectories(): array
    {
        return $this->get(self::EXPORT_DIRECTORIES, []);
    }
}
