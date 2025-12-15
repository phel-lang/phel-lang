<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

final class BuildConfig extends AbstractConfig implements BuildConfigInterface
{
    /**
     * @return list<string>
     */
    public function getPathsToIgnore(): array
    {
        return $this->get(PhelConfig::IGNORE_WHEN_BUILDING, []);
    }

    /**
     * @return list<string>
     */
    public function getPathsToAvoidCache(): array
    {
        return $this->get(PhelConfig::NO_CACHE_WHEN_BUILDING, []);
    }

    public function shouldCreateEntryPointPhpFile(): bool
    {
        return $this->getPhelBuildConfig()->shouldCreateEntryPointPhpFile();
    }

    public function getPhelBuildConfig(): PhelBuildConfig
    {
        return PhelBuildConfig::fromArray((array)$this->get('out', []));
    }

    public function isNamespaceCacheEnabled(): bool
    {
        return (bool)$this->get(PhelConfig::ENABLE_NAMESPACE_CACHE, true);
    }

    public function isCompiledCodeCacheEnabled(): bool
    {
        return (bool)$this->get(PhelConfig::ENABLE_COMPILED_CODE_CACHE, true);
    }

    public function getTempDir(): string
    {
        return (string)$this->get(PhelConfig::TEMP_DIR, sys_get_temp_dir() . '/phel');
    }

    public function getCacheDir(): string
    {
        $cacheDir = (string)$this->get(PhelConfig::CACHE_DIR, 'cache');

        // If absolute path, use as-is; otherwise relative to app root
        if (str_starts_with($cacheDir, '/') || str_starts_with($cacheDir, 'phar://')) {
            return $cacheDir;
        }

        return $this->getAppRootDir() . '/' . $cacheDir;
    }

    public function getNamespaceCacheFile(): string
    {
        return $this->getCacheDir() . '/namespace-cache.php';
    }

    public function getDependencyGraphCacheFile(): string
    {
        return $this->getCacheDir() . '/dependency-graph.php';
    }

    public function isDependencyGraphCacheEnabled(): bool
    {
        return (bool)$this->get(PhelConfig::ENABLE_DEPENDENCY_GRAPH_CACHE, true);
    }
}
