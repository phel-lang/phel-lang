<?php

declare(strict_types=1);

namespace Phel\Runtime\Loader;

final class RootPhelConfig
{
    private const PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private string $applicationRootDir;

    /**
     * @var null|array{
     *     loader:array|null,
     *     loader-dev:array|null,
     *     vendor-dir:string|null,
     * }
     */
    private ?array $rootPhelConfig = null;

    public function __construct(string $applicationRootDir)
    {
        $this->applicationRootDir = $applicationRootDir;
    }

    /**
     * @return array{
     *     loader:array|null,
     *     loader-dev:array|null,
     *     vendor-dir:string|null,
     * }
     */
    public function getRootPhelConfig(): array
    {
        $rootPhelConfigPath = $this->applicationRootDir . self::PHEL_CONFIG_FILE_NAME;

        if ($this->rootPhelConfig === null) {
            $this->rootPhelConfig = is_file($rootPhelConfigPath)
                ? require $rootPhelConfigPath
                : [];
        }

        return $this->rootPhelConfig;
    }
}
