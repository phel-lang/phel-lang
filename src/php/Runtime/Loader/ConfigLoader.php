<?php

declare(strict_types=1);

namespace Phel\Runtime\Loader;

final class ConfigLoader
{
    private const PHEL_CONFIG_FILE_NAME = 'phel-config.php';

    private RootPhelConfig $rootPhelConfig;
    private VendorDir $vendorDir;
    private ConfigNormalizer $configNormalizer;

    public function __construct(
        RootPhelConfig $rootPhelConfig,
        VendorDir $vendorDir,
        ConfigNormalizer $configNormalizer
    ) {
        $this->rootPhelConfig = $rootPhelConfig;
        $this->vendorDir = $vendorDir;
        $this->configNormalizer = $configNormalizer;
    }

    public function loadConfig(): array
    {
        $loaderConfig = [
            $this->loadRootConfig(),
            $this->loadVendorConfig(),
        ];

        /** @var array<string, list<string>> $flatConfig [ns => [path1, path2, ...]] */
        $flatConfig = array_merge(...$loaderConfig);

        return $flatConfig;
    }

    /**
     * Uses 'loader' and 'loader-dev' from the phel config.
     *
     * @return array<string, list<string>>
     */
    private function loadRootConfig(): array
    {
        $result = [];
        $pathPrefix = '/..';
        $config = $this->rootPhelConfig->getRootPhelConfig();

        $result[] = $this->configNormalizer->normalize($config['loader'] ?? [], $pathPrefix);
        $result[] = $this->configNormalizer->normalize($config['loader-dev'] ?? [], $pathPrefix);

        return array_merge(...$result);
    }

    /**
     * Uses only 'loader' from the phel config.
     *
     * @return array<string, list<string>>
     */
    private function loadVendorConfig(): array
    {
        $pattern = $this->vendorDir->getVendorDir() . '/*/*/' . self::PHEL_CONFIG_FILE_NAME;

        $result = [];

        foreach (glob($pattern) as $phelConfigPath) {
            $pathPrefix = '/' . basename(dirname($phelConfigPath));
            /** @psalm-suppress UnresolvableInclude */
            $config = (require $phelConfigPath)['loader'] ?? [];
            $result[] = $this->configNormalizer->normalize($config, $pathPrefix);
        }

        return array_merge(...$result);
    }
}
