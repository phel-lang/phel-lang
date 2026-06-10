<?php

declare(strict_types=1);

namespace Phel\Interop;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelConfig;
use Phel\Config\PhelExportConfig;
use Phel\Shared\ScalarCoercion;

use function is_array;

final class InteropConfig extends AbstractConfig
{
    private const array DEFAULT_EXPORT_DIRECTORIES = ['src'];

    private const string DEFAULT_EXPORT_NAMESPACE_PREFIX = 'PhelGenerated';

    private const string DEFAULT_EXPORT_TARGET_DIRECTORY = 'src/PhelGenerated';

    private const array DEFAULT_EXPORT = [
        PhelExportConfig::FROM_DIRECTORIES => self::DEFAULT_EXPORT_DIRECTORIES,
        PhelExportConfig::NAMESPACE_PREFIX => self::DEFAULT_EXPORT_NAMESPACE_PREFIX,
        PhelExportConfig::TARGET_DIRECTORY => self::DEFAULT_EXPORT_TARGET_DIRECTORY,
    ];

    public function prefixNamespace(): string
    {
        return ScalarCoercion::toString(
            $this->getExport()[PhelExportConfig::NAMESPACE_PREFIX] ?? null,
            self::DEFAULT_EXPORT_NAMESPACE_PREFIX,
        );
    }

    public function getExportTargetDirectory(): string
    {
        return ScalarCoercion::toString(
            $this->getExport()[PhelExportConfig::TARGET_DIRECTORY] ?? null,
            self::DEFAULT_EXPORT_TARGET_DIRECTORY,
        );
    }

    /**
     * @return list<string>
     */
    public function getExportDirectories(): array
    {
        $fromDirectories = $this->getExport()[PhelExportConfig::FROM_DIRECTORIES] ?? self::DEFAULT_EXPORT_DIRECTORIES;
        if (!is_array($fromDirectories)) {
            $fromDirectories = self::DEFAULT_EXPORT_DIRECTORIES;
        }

        return array_values(array_map(
            fn(mixed $dir): string => $this->getAppRootDir() . '/' . ScalarCoercion::toString($dir),
            $fromDirectories,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function getExport(): array
    {
        $export = $this->get(PhelConfig::EXPORT_CONFIG, self::DEFAULT_EXPORT);

        return is_array($export) ? $export : self::DEFAULT_EXPORT;
    }
}
