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
        $config = PhelBuildConfig::fromArray((array)$this->get('out', []));

        // Auto-detect namespace from core.phel if not explicitly configured
        if ($config->getMainPhelNamespace() === '') {
            $detected = $this->autoDetectMainNamespace();
            if ($detected !== '') {
                $config->setMainPhelNamespace($detected);
            }
        }

        return $config;
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

    /**
     * Auto-detect the main namespace from conventional entry point files.
     * Looks for core.phel or main.phel in source directories.
     */
    private function autoDetectMainNamespace(): string
    {
        $srcDirs = (array)$this->get(PhelConfig::SRC_DIRS, ['src/phel']);
        $appRoot = $this->getAppRootDir();

        foreach ($srcDirs as $srcDir) {
            foreach (['core.phel', 'main.phel'] as $entryFile) {
                $path = $appRoot . '/' . $srcDir . '/' . $entryFile;
                if (file_exists($path)) {
                    $namespace = $this->parseNamespaceFromFile($path);
                    if ($namespace !== '') {
                        return $namespace;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Parse the namespace from a Phel file's (ns ...) declaration.
     */
    private function parseNamespaceFromFile(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return '';
        }

        // Match (ns namespace-name) at the start of the file
        if (preg_match('/^\s*\(ns\s+([^\s\)]+)/', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
