<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;

use function sprintf;

final class PhelConfig implements JsonSerializable
{
    public const string SRC_DIRS = 'src-dirs';

    public const string TEST_DIRS = 'test-dirs';

    public const string VENDOR_DIR = 'vendor-dir';

    public const string BUILD_CONFIG = 'out';

    public const string ERROR_LOG_FILE = 'error-log-file';

    public const string EXPORT_CONFIG = 'export';

    public const string IGNORE_WHEN_BUILDING = 'ignore-when-building';

    public const string NO_CACHE_WHEN_BUILDING = 'no-cache-when-building';

    public const string KEEP_GENERATED_TEMP_FILES = 'keep-generated-temp-files';

    public const string TEMP_DIR = 'temp-dir';

    public const string FORMAT_DIRS = 'format-dirs';

    public const string ASSERTS_ENABLED = 'asserts-enabled';

    public const string ENABLE_NAMESPACE_CACHE = 'enable-namespace-cache';

    public const string ENABLE_COMPILED_CODE_CACHE = 'enable-compiled-code-cache';

    public const string CACHE_DIR = 'cache-dir';

    /** @var list<string> */
    public const array DEFAULT_SRC_DIRS = ['src'];

    private const string PHEL_TEMP_SUBDIR = '/phel';

    /** @var list<string> */
    private array $srcDirs = self::DEFAULT_SRC_DIRS;

    /** @var list<string> */
    private array $testDirs = ['tests'];

    private string $vendorDir = 'vendor';

    private string $errorLogFile = '/tmp/phel-error.log';

    private PhelExportConfig $exportConfig;

    private PhelBuildConfig $buildConfig;

    /** @var list<string> */
    private array $ignoreWhenBuilding = [];

    /** @var list<string> */
    private array $noCacheWhenBuilding = [];

    private bool $keepGeneratedTempFiles = false;

    private string $tempDir;

    private string $cacheDir;

    /** @var list<string> */
    private array $formatDirs = ['src', 'tests'];

    private bool $enableAsserts = true;

    private bool $enableNamespaceCache = true;

    private bool $enableCompiledCodeCache = true;

    public function __construct()
    {
        $this->exportConfig = new PhelExportConfig();
        $this->buildConfig = new PhelBuildConfig();

        // Single syscall for temp directory
        $baseTemp = sys_get_temp_dir() . self::PHEL_TEMP_SUBDIR;
        $this->tempDir = $baseTemp . '/tmp';
        $this->cacheDir = $baseTemp . '/cache';
    }

    /**
     * Quick factory for typical project setup.
     *
     * - Pass `$layout` to force a specific layout. When omitted, the layout is
     *   auto-detected from the current working directory: `src/phel/` →
     *   Nested, `src/` → Flat, otherwise → Root.
     * - `$mainNamespace` is optional; when left blank, the build step infers it
     *   from `core.phel` / `main.phel` at the configured source roots.
     *
     * Examples:
     *   return PhelConfig::forProject();                                 // zero-config, auto-detects layout + namespace
     *   return PhelConfig::forProject('my-app\core');                    // explicit namespace
     *   return PhelConfig::forProject(layout: ProjectLayout::Root);      // single-file / scratch project
     *   return PhelConfig::forProject('my-app\main', ProjectLayout::Nested);
     */
    public static function forProject(
        string $mainNamespace = '',
        ?ProjectLayout $layout = null,
    ): self {
        $config = new self();
        $config->useLayout($layout ?? self::detectLayout());

        if ($mainNamespace !== '') {
            $config->setMainPhelNamespace($mainNamespace);
        }

        return $config;
    }

    // ========================================
    // Getters
    // ========================================

    /**
     * @return list<string>
     */
    public function getSrcDirs(): array
    {
        return $this->srcDirs;
    }

    /**
     * @return list<string>
     */
    public function getTestDirs(): array
    {
        return $this->testDirs;
    }

    public function getVendorDir(): string
    {
        return $this->vendorDir;
    }

    public function getErrorLogFile(): string
    {
        return $this->errorLogFile;
    }

    public function getBuildConfig(): PhelBuildConfig
    {
        return $this->buildConfig;
    }

    public function getExportConfig(): PhelExportConfig
    {
        return $this->exportConfig;
    }

    /**
     * @return list<string>
     */
    public function getIgnoreWhenBuilding(): array
    {
        return $this->ignoreWhenBuilding;
    }

    /**
     * @return list<string>
     */
    public function getNoCacheWhenBuilding(): array
    {
        return $this->noCacheWhenBuilding;
    }

    public function getKeepGeneratedTempFiles(): bool
    {
        return $this->keepGeneratedTempFiles;
    }

    public function getTempDir(): string
    {
        return $this->tempDir;
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * @return list<string>
     */
    public function getFormatDirs(): array
    {
        return $this->formatDirs;
    }

    public function isAssertsEnabled(): bool
    {
        return $this->enableAsserts;
    }

    public function isNamespaceCacheEnabled(): bool
    {
        return $this->enableNamespaceCache;
    }

    public function isCompiledCodeCacheEnabled(): bool
    {
        return $this->enableCompiledCodeCache;
    }

    // ========================================
    // Layout Configuration
    // ========================================

    /**
     * Apply a project layout (nested or flat).
     */
    public function useLayout(ProjectLayout $layout): self
    {
        $this->srcDirs = [$layout->getSrcDir()];
        $this->testDirs = [$layout->getTestDir()];
        $this->formatDirs = $layout->getFormatDirs();
        $this->exportConfig->setFromDirectories($layout->getExportFromDirs());

        return $this;
    }

    /**
     * Use nested directory layout: src/phel and tests/phel.
     * Useful when the project also hosts PHP sources alongside Phel.
     */
    public function useNestedLayout(): self
    {
        return $this->useLayout(ProjectLayout::Nested);
    }

    /**
     * Use flat directory layout: src and tests (default, simpler projects).
     */
    public function useFlatLayout(): self
    {
        return $this->useLayout(ProjectLayout::Flat);
    }

    // ========================================
    // Convenience Setters
    // ========================================

    /**
     * Direct setter for the main Phel namespace (convenience method).
     * Automatically configures build output to out/index.php.
     */
    public function setMainPhelNamespace(string $namespace): self
    {
        $this->buildConfig->setMainPhelNamespace($namespace);
        if ($this->buildConfig->getMainPhpPath() === 'out/index.php' || $this->buildConfig->getMainPhpPath() === '') {
            $this->buildConfig->setMainPhpPath('out/index.php');
        }

        return $this;
    }

    /**
     * Direct setter for the main PHP output path (convenience method).
     */
    public function setMainPhpPath(string $path): self
    {
        $this->buildConfig->setMainPhpPath($path);

        return $this;
    }

    /**
     * Direct setter for the build destination directory (convenience method).
     */
    public function setBuildDestDir(string $dir): self
    {
        $this->buildConfig->setDestDir($dir);

        return $this;
    }

    /**
     * Direct setter for export namespace prefix (convenience method).
     */
    public function setExportNamespacePrefix(string $prefix): self
    {
        $this->exportConfig->setNamespacePrefix($prefix);

        return $this;
    }

    /**
     * Direct setter for export target directory (convenience method).
     */
    public function setExportTargetDirectory(string $dir): self
    {
        $this->exportConfig->setTargetDirectory($dir);

        return $this;
    }

    /**
     * Direct setter for export from directories (convenience method).
     */
    public function setExportFromDirectories(array $dirs): self
    {
        $this->exportConfig->setFromDirectories($dirs);

        return $this;
    }

    // ========================================
    // Standard Setters
    // ========================================

    /**
     * @param list<string> $list
     */
    public function setSrcDirs(array $list): self
    {
        $this->srcDirs = $list;

        return $this;
    }

    /**
     * @param list<string> $list
     */
    public function setTestDirs(array $list): self
    {
        $this->testDirs = $list;

        return $this;
    }

    public function setVendorDir(string $dir): self
    {
        $this->vendorDir = $dir;

        return $this;
    }

    public function setExportConfig(PhelExportConfig $exportConfig): self
    {
        $this->exportConfig = $exportConfig;

        return $this;
    }

    public function setErrorLogFile(string $filepath): self
    {
        $this->errorLogFile = $filepath;

        return $this;
    }

    public function setBuildConfig(PhelBuildConfig $buildConfig): self
    {
        $this->buildConfig = $buildConfig;

        return $this;
    }

    /**
     * @param list<string> $list
     */
    public function setIgnoreWhenBuilding(array $list): self
    {
        $this->ignoreWhenBuilding = $list;

        return $this;
    }

    public function setKeepGeneratedTempFiles(bool $flag): self
    {
        $this->keepGeneratedTempFiles = $flag;

        return $this;
    }

    public function setTempDir(string $dir): self
    {
        $this->tempDir = rtrim($dir, DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * @param list<string> $list
     */
    public function setFormatDirs(array $list): self
    {
        $this->formatDirs = $list;

        return $this;
    }

    /**
     * @param list<string> $list
     */
    public function setNoCacheWhenBuilding(array $list): self
    {
        $this->noCacheWhenBuilding = $list;

        return $this;
    }

    public function setEnableAsserts(bool $flag): self
    {
        $this->enableAsserts = $flag;

        return $this;
    }

    public function setEnableNamespaceCache(bool $flag): self
    {
        $this->enableNamespaceCache = $flag;

        return $this;
    }

    public function setEnableCompiledCodeCache(bool $flag): self
    {
        $this->enableCompiledCodeCache = $flag;

        return $this;
    }

    public function setCacheDir(string $dir): self
    {
        $this->cacheDir = rtrim($dir, DIRECTORY_SEPARATOR);

        return $this;
    }

    // ========================================
    // Validation
    // ========================================

    /**
     * Validate the configuration and return any errors found.
     *
     * @return list<string> List of validation errors (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        foreach ($this->srcDirs as $dir) {
            if (str_starts_with($dir, '/')) {
                $errors[] = sprintf("Source directory '%s' should be relative, not absolute", $dir);
            }
        }

        foreach ($this->testDirs as $dir) {
            if (str_starts_with($dir, '/')) {
                $errors[] = sprintf("Test directory '%s' should be relative, not absolute", $dir);
            }
        }

        if ($this->vendorDir !== '' && str_starts_with($this->vendorDir, '/')) {
            $errors[] = sprintf("Vendor directory '%s' should be relative, not absolute", $this->vendorDir);
        }

        return $errors;
    }

    // ========================================
    // Serialization
    // ========================================

    public function jsonSerialize(): array
    {
        return [
            self::SRC_DIRS => $this->srcDirs,
            self::TEST_DIRS => $this->testDirs,
            self::VENDOR_DIR => $this->vendorDir,
            self::ERROR_LOG_FILE => $this->errorLogFile,
            self::BUILD_CONFIG => $this->buildConfig->jsonSerialize(),
            self::EXPORT_CONFIG => $this->exportConfig->jsonSerialize(),
            self::IGNORE_WHEN_BUILDING => $this->ignoreWhenBuilding,
            self::NO_CACHE_WHEN_BUILDING => $this->noCacheWhenBuilding,
            self::KEEP_GENERATED_TEMP_FILES => $this->keepGeneratedTempFiles,
            self::TEMP_DIR => $this->tempDir,
            self::FORMAT_DIRS => $this->formatDirs,
            self::ASSERTS_ENABLED => $this->enableAsserts,
            self::ENABLE_NAMESPACE_CACHE => $this->enableNamespaceCache,
            self::ENABLE_COMPILED_CODE_CACHE => $this->enableCompiledCodeCache,
            self::CACHE_DIR => $this->cacheDir,
        ];
    }

    /**
     * Auto-detect the most likely layout from the current working directory.
     * Falls back to Flat when the cwd is not available or probing fails.
     */
    private static function detectLayout(): ProjectLayout
    {
        $cwd = getcwd();
        if ($cwd === false) {
            return ProjectLayout::Flat;
        }

        if (is_dir($cwd . '/src/phel')) {
            return ProjectLayout::Nested;
        }

        if (is_dir($cwd . '/src')) {
            return ProjectLayout::Flat;
        }

        return ProjectLayout::Root;
    }
}
