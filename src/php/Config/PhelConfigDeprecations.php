<?php

declare(strict_types=1);

namespace Phel\Config;

use Deprecated;

/**
 * Backward-compatibility shims for {@see PhelConfig}.
 *
 * Every method here is deprecated since 0.37 and delegates to the matching
 * immutable `with*()` method on the host class. They are permanent aliases
 * (removal would require a major version bump); extracted into a trait so they
 * do not clutter PhelConfig's canonical API.
 *
 * @mixin PhelConfig
 */
trait PhelConfigDeprecations
{
    #[Deprecated(message: 'since 0.37, use withLayout()')]
    public function useLayout(ProjectLayout $layout): self
    {
        return $this->withLayout($layout);
    }

    #[Deprecated(message: 'since 0.37, use withLayout(ProjectLayout::Nested)')]
    public function useNestedLayout(): self
    {
        return $this->withLayout(ProjectLayout::Nested);
    }

    #[Deprecated(message: 'since 0.37, use withLayout(ProjectLayout::Flat)')]
    public function useFlatLayout(): self
    {
        return $this->withLayout(ProjectLayout::Flat);
    }

    /**
     * @param list<string> $list
     */
    #[Deprecated(message: 'since 0.37, use withSrcDirs()')]
    public function setSrcDirs(array $list): self
    {
        return $this->withSrcDirs($list);
    }

    /**
     * @param list<string> $list
     */
    #[Deprecated(message: 'since 0.37, use withTestDirs()')]
    public function setTestDirs(array $list): self
    {
        return $this->withTestDirs($list);
    }

    #[Deprecated(message: 'since 0.37, use withVendorDir()')]
    public function setVendorDir(string $dir): self
    {
        return $this->withVendorDir($dir);
    }

    #[Deprecated(message: 'since 0.37, use withErrorLogFile()')]
    public function setErrorLogFile(string $filepath): self
    {
        return $this->withErrorLogFile($filepath);
    }

    #[Deprecated(message: 'since 0.37, use withBuildConfig()')]
    public function setBuildConfig(PhelBuildConfig $buildConfig): self
    {
        return $this->withBuildConfig($buildConfig);
    }

    #[Deprecated(message: 'since 0.37, use withExportConfig()')]
    public function setExportConfig(PhelExportConfig $exportConfig): self
    {
        return $this->withExportConfig($exportConfig);
    }

    #[Deprecated(message: 'since 0.37, use withMainPhelNamespace()')]
    public function setMainPhelNamespace(string $namespace): self
    {
        return $this->withMainPhelNamespace($namespace);
    }

    #[Deprecated(message: 'since 0.37, use withMainPhpPath()')]
    public function setMainPhpPath(string $path): self
    {
        return $this->withMainPhpPath($path);
    }

    #[Deprecated(message: 'since 0.37, use withBuildDestDir()')]
    public function setBuildDestDir(string $dir): self
    {
        return $this->withBuildDestDir($dir);
    }

    #[Deprecated(message: 'since 0.37, use withExportNamespacePrefix()')]
    public function setExportNamespacePrefix(string $prefix): self
    {
        return $this->withExportNamespacePrefix($prefix);
    }

    #[Deprecated(message: 'since 0.37, use withExportTargetDirectory()')]
    public function setExportTargetDirectory(string $dir): self
    {
        return $this->withExportTargetDirectory($dir);
    }

    /**
     * @param list<string> $dirs
     */
    #[Deprecated(message: 'since 0.37, use withExportFromDirectories()')]
    public function setExportFromDirectories(array $dirs): self
    {
        return $this->withExportFromDirectories($dirs);
    }

    /**
     * @param list<string> $list
     */
    #[Deprecated(message: 'since 0.37, use withIgnoreWhenBuilding()')]
    public function setIgnoreWhenBuilding(array $list): self
    {
        return $this->withIgnoreWhenBuilding($list);
    }

    /**
     * @param list<string> $list
     */
    #[Deprecated(message: 'since 0.37, use withNoCacheWhenBuilding()')]
    public function setNoCacheWhenBuilding(array $list): self
    {
        return $this->withNoCacheWhenBuilding($list);
    }

    #[Deprecated(message: 'since 0.37, use withKeepGeneratedTempFiles()')]
    public function setKeepGeneratedTempFiles(bool $flag): self
    {
        return $this->withKeepGeneratedTempFiles($flag);
    }

    #[Deprecated(message: 'since 0.37, use withTempDir()')]
    public function setTempDir(string $dir): self
    {
        return $this->withTempDir($dir);
    }

    #[Deprecated(message: 'since 0.37, use withCacheDir()')]
    public function setCacheDir(string $dir): self
    {
        return $this->withCacheDir($dir);
    }

    /**
     * @param list<string> $list
     */
    #[Deprecated(message: 'since 0.37, use withFormatDirs()')]
    public function setFormatDirs(array $list): self
    {
        return $this->withFormatDirs($list);
    }

    #[Deprecated(message: 'since 0.37, use withEnableAsserts()')]
    public function setEnableAsserts(bool $flag): self
    {
        return $this->withEnableAsserts($flag);
    }

    #[Deprecated(message: 'since 0.37, use withWarnDeprecations()')]
    public function setWarnDeprecations(bool $flag): self
    {
        return $this->withWarnDeprecations($flag);
    }

    #[Deprecated(message: 'since 0.37, use withEnableNamespaceCache()')]
    public function setEnableNamespaceCache(bool $flag): self
    {
        return $this->withEnableNamespaceCache($flag);
    }

    #[Deprecated(message: 'since 0.37, use withEnableCompiledCodeCache()')]
    public function setEnableCompiledCodeCache(bool $flag): self
    {
        return $this->withEnableCompiledCodeCache($flag);
    }

    #[Deprecated(message: 'since 0.37, use withPhelDir()')]
    public function setPhelDir(string $dir): self
    {
        return $this->withPhelDir($dir);
    }
}
