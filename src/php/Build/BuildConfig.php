<?php

declare(strict_types=1);

namespace Phel\Build;

use Gacela\Framework\AbstractConfig;
use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;
use Phel\Shared\CompileOptions;
use Phel\Shared\PhelProjectDirectory;
use Phel\Shared\ScalarCoercion;

use function is_string;

final class BuildConfig extends AbstractConfig implements BuildConfigInterface
{
    /**
     * @return list<string>
     */
    public function getPathsToIgnore(): array
    {
        return ScalarCoercion::toStringList($this->get(PhelConfig::IGNORE_WHEN_BUILDING, []));
    }

    /**
     * @return list<string>
     */
    public function getPathsToAvoidCache(): array
    {
        return ScalarCoercion::toStringList($this->get(PhelConfig::NO_CACHE_WHEN_BUILDING, []));
    }

    public function shouldCreateEntryPointPhpFile(): bool
    {
        return $this->getPhelBuildConfig()->shouldCreateEntryPointPhpFile();
    }

    public function getPhelBuildConfig(): PhelBuildConfig
    {
        /** @var array<string, mixed> $out */
        $out = (array) $this->get('out', []);
        $config = PhelBuildConfig::fromArray($out);

        // Auto-detect namespace from core.phel if not explicitly configured
        if ($config->getMainPhelNamespace() === '') {
            $detected = $this->autoDetectMainNamespace();
            if ($detected !== '') {
                $config = $config->withMainPhelNamespace($detected);
            }
        }

        return $config;
    }

    public function getOptimizationLevel(): int
    {
        return max(0, ScalarCoercion::toInt($this->get(PhelConfig::OPTIMIZATION_LEVEL, CompileOptions::DEFAULT_OPTIMIZATION_LEVEL)));
    }

    public function isNamespaceCacheEnabled(): bool
    {
        return (bool) $this->get(PhelConfig::ENABLE_NAMESPACE_CACHE, true);
    }

    public function isCompiledCodeCacheEnabled(): bool
    {
        return (bool) $this->get(PhelConfig::ENABLE_COMPILED_CODE_CACHE, true);
    }

    public function getTempDir(): string
    {
        return ScalarCoercion::toString($this->get(PhelConfig::TEMP_DIR, sys_get_temp_dir() . '/phel'));
    }

    public function getCacheDir(): string
    {
        $envOverride = getenv('PHEL_CACHE_DIR');
        if (is_string($envOverride) && $envOverride !== '') {
            return $envOverride;
        }

        $cacheDir = ScalarCoercion::toString($this->get(PhelConfig::CACHE_DIR, '.phel/cache'));
        $phelDir = ScalarCoercion::toString($this->get(PhelConfig::PHEL_DIR, ''));

        return PhelProjectDirectory::resolve($this->getAppRootDir(), $cacheDir, $phelDir);
    }

    public function getNamespaceCacheFile(): string
    {
        return $this->getCacheDir() . '/namespace-cache.php';
    }

    public function getScanIndexCacheFile(): string
    {
        return $this->getCacheDir() . '/scan-index.php';
    }

    /**
     * Auto-detect the main namespace from conventional entry point files.
     * Looks for core.phel or main.phel in source directories.
     */
    private function autoDetectMainNamespace(): string
    {
        /** @var list<string> $srcDirs */
        $srcDirs = (array) $this->get(PhelConfig::SRC_DIRS, PhelConfig::DEFAULT_SRC_DIRS);
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
