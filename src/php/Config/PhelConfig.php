<?php

declare(strict_types=1);

namespace Phel\Config;

use Deprecated;
use JsonSerializable;

final readonly class PhelConfig implements JsonSerializable
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

    public const string WARN_DEPRECATIONS = 'warn-deprecations';

    public const string ENABLE_NAMESPACE_CACHE = 'enable-namespace-cache';

    public const string ENABLE_COMPILED_CODE_CACHE = 'enable-compiled-code-cache';

    public const string CACHE_DIR = 'cache-dir';

    public const string PHEL_DIR = 'phel-dir';

    /** @var list<string> */
    public const array DEFAULT_SRC_DIRS = ['src'];

    private const string PHEL_TEMP_SUBDIR = '/phel';

    private const string DEFAULT_CACHE_DIR = '.phel/cache';

    public string $tempDir;

    /**
     * @param list<string> $srcDirs
     * @param list<string> $testDirs
     * @param list<string> $ignoreWhenBuilding
     * @param list<string> $noCacheWhenBuilding
     * @param list<string> $formatDirs
     */
    public function __construct(
        public array $srcDirs = self::DEFAULT_SRC_DIRS,
        public array $testDirs = ['tests'],
        public string $vendorDir = 'vendor',
        public string $errorLogFile = '.phel/error.log',
        public PhelExportConfig $exportConfig = new PhelExportConfig(),
        public PhelBuildConfig $buildConfig = new PhelBuildConfig(),
        public array $ignoreWhenBuilding = [],
        public array $noCacheWhenBuilding = [],
        public bool $keepGeneratedTempFiles = false,
        ?string $tempDir = null,
        public string $cacheDir = self::DEFAULT_CACHE_DIR,
        public array $formatDirs = ['src', 'tests'],
        public bool $enableAsserts = true,
        public bool $warnDeprecations = false,
        public bool $enableNamespaceCache = true,
        public bool $enableCompiledCodeCache = true,
        public string $phelDir = '',
    ) {
        $this->tempDir = $tempDir === null
            ? sys_get_temp_dir() . self::PHEL_TEMP_SUBDIR . '/tmp'
            : rtrim($tempDir, DIRECTORY_SEPARATOR);
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
     *   return PhelConfig::forProject();                                 // zero-config
     *   return PhelConfig::forProject('my-app\core');                    // explicit namespace
     *   return PhelConfig::forProject(layout: ProjectLayout::Root);      // single-file / scratch project
     *   return PhelConfig::forProject('my-app\main', ProjectLayout::Nested);
     */
    public static function forProject(
        string $mainNamespace = '',
        ?ProjectLayout $layout = null,
    ): self {
        $config = new self()->withLayout($layout ?? self::detectLayout());

        if ($mainNamespace !== '') {
            return $config->withMainPhelNamespace($mainNamespace);
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

    public function shouldWarnDeprecations(): bool
    {
        return $this->warnDeprecations;
    }

    public function isNamespaceCacheEnabled(): bool
    {
        return $this->enableNamespaceCache;
    }

    public function isCompiledCodeCacheEnabled(): bool
    {
        return $this->enableCompiledCodeCache;
    }

    public function getPhelDir(): string
    {
        return $this->phelDir;
    }

    // ========================================
    // Layout
    // ========================================

    /**
     * Apply a project layout (nested or flat). Returns a new instance with src,
     * test, format, and export-from directories aligned with the layout.
     */
    public function withLayout(ProjectLayout $layout): self
    {
        return $this->with([
            'srcDirs' => [$layout->getSrcDir()],
            'testDirs' => [$layout->getTestDir()],
            'formatDirs' => $layout->getFormatDirs(),
            'exportConfig' => $this->exportConfig->withFromDirectories($layout->getExportFromDirs()),
        ]);
    }

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

    // ========================================
    // Immutable with*() API
    // ========================================

    /**
     * @param list<string> $list
     */
    public function withSrcDirs(array $list): self
    {
        return $this->with(['srcDirs' => $list]);
    }

    /**
     * @param list<string> $list
     */
    public function withTestDirs(array $list): self
    {
        return $this->with(['testDirs' => $list]);
    }

    public function withVendorDir(string $dir): self
    {
        return $this->with(['vendorDir' => $dir]);
    }

    public function withErrorLogFile(string $filepath): self
    {
        return $this->with(['errorLogFile' => $filepath]);
    }

    public function withBuildConfig(PhelBuildConfig $buildConfig): self
    {
        return $this->with(['buildConfig' => $buildConfig]);
    }

    public function withExportConfig(PhelExportConfig $exportConfig): self
    {
        return $this->with(['exportConfig' => $exportConfig]);
    }

    /**
     * Flattens the build namespace onto PhelConfig. Sets the entry-point PHP
     * path to `out/index.php` when none has been configured yet.
     */
    public function withMainPhelNamespace(string $namespace): self
    {
        $build = $this->buildConfig->withMainPhelNamespace($namespace);
        $currentPath = $this->buildConfig->getMainPhpPath();
        if ($currentPath === 'out/index.php' || $currentPath === '') {
            $build = $build->withMainPhpPath('out/index.php');
        }

        return $this->with(['buildConfig' => $build]);
    }

    public function withMainPhpPath(string $path): self
    {
        return $this->with(['buildConfig' => $this->buildConfig->withMainPhpPath($path)]);
    }

    public function withBuildDestDir(string $dir): self
    {
        return $this->with(['buildConfig' => $this->buildConfig->withDestDir($dir)]);
    }

    public function withExportNamespacePrefix(string $prefix): self
    {
        return $this->with(['exportConfig' => $this->exportConfig->withNamespacePrefix($prefix)]);
    }

    public function withExportTargetDirectory(string $dir): self
    {
        return $this->with(['exportConfig' => $this->exportConfig->withTargetDirectory($dir)]);
    }

    /**
     * @param list<string> $dirs
     */
    public function withExportFromDirectories(array $dirs): self
    {
        return $this->with(['exportConfig' => $this->exportConfig->withFromDirectories($dirs)]);
    }

    /**
     * @param list<string> $list
     */
    public function withIgnoreWhenBuilding(array $list): self
    {
        return $this->with(['ignoreWhenBuilding' => $list]);
    }

    /**
     * @param list<string> $list
     */
    public function withNoCacheWhenBuilding(array $list): self
    {
        return $this->with(['noCacheWhenBuilding' => $list]);
    }

    public function withKeepGeneratedTempFiles(bool $flag = true): self
    {
        return $this->with(['keepGeneratedTempFiles' => $flag]);
    }

    public function withTempDir(string $dir): self
    {
        return $this->with(['tempDir' => rtrim($dir, DIRECTORY_SEPARATOR)]);
    }

    public function withCacheDir(string $dir): self
    {
        return $this->with(['cacheDir' => rtrim($dir, DIRECTORY_SEPARATOR)]);
    }

    /**
     * @param list<string> $list
     */
    public function withFormatDirs(array $list): self
    {
        return $this->with(['formatDirs' => $list]);
    }

    public function withEnableAsserts(bool $flag = true): self
    {
        return $this->with(['enableAsserts' => $flag]);
    }

    public function withWarnDeprecations(bool $flag = true): self
    {
        return $this->with(['warnDeprecations' => $flag]);
    }

    public function withEnableNamespaceCache(bool $flag = true): self
    {
        return $this->with(['enableNamespaceCache' => $flag]);
    }

    public function withEnableCompiledCodeCache(bool $flag = true): self
    {
        return $this->with(['enableCompiledCodeCache' => $flag]);
    }

    /**
     * Redirect the entire per-project state directory (`.phel/` by default)
     * to a different location. Useful when the project lives behind a web
     * server: e.g. a WordPress plugin can move state out of the document
     * root via `withPhelDir('/var/cache/phel')`. Honors `PHEL_DIR` env var
     * as a higher-priority override.
     */
    public function withPhelDir(string $dir): self
    {
        return $this->with(['phelDir' => $dir]);
    }

    // ========================================
    // Deprecated mutating setters (shim → with*())
    // ========================================
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
        return new PhelConfigValidator()->validate($this->srcDirs, $this->testDirs, $this->vendorDir);
    }

    // ========================================
    // Serialization
    // ========================================

    /**
     * @return array<string, mixed>
     */
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
            self::WARN_DEPRECATIONS => $this->warnDeprecations,
            self::ENABLE_NAMESPACE_CACHE => $this->enableNamespaceCache,
            self::ENABLE_COMPILED_CODE_CACHE => $this->enableCompiledCodeCache,
            self::CACHE_DIR => $this->cacheDir,
            self::PHEL_DIR => $this->phelDir,
        ];
    }

    /**
     * Returns a new instance with the supplied fields overridden. Internal
     * plumbing for every `with*()` method — keeps each wither one line.
     *
     * @param array<string, mixed> $overrides
     */
    private function with(array $overrides): self
    {
        $base = [
            'srcDirs' => $this->srcDirs,
            'testDirs' => $this->testDirs,
            'vendorDir' => $this->vendorDir,
            'errorLogFile' => $this->errorLogFile,
            'exportConfig' => $this->exportConfig,
            'buildConfig' => $this->buildConfig,
            'ignoreWhenBuilding' => $this->ignoreWhenBuilding,
            'noCacheWhenBuilding' => $this->noCacheWhenBuilding,
            'keepGeneratedTempFiles' => $this->keepGeneratedTempFiles,
            'tempDir' => $this->tempDir,
            'cacheDir' => $this->cacheDir,
            'formatDirs' => $this->formatDirs,
            'enableAsserts' => $this->enableAsserts,
            'warnDeprecations' => $this->warnDeprecations,
            'enableNamespaceCache' => $this->enableNamespaceCache,
            'enableCompiledCodeCache' => $this->enableCompiledCodeCache,
            'phelDir' => $this->phelDir,
        ];

        return new self(...[...$base, ...$overrides]);
    }

    /**
     * Auto-detect the most likely layout from the current working directory.
     * Falls back to Flat when the cwd is not available or probing fails.
     */
    private static function detectLayout(): ProjectLayout
    {
        return new ProjectLayoutDetector()->detectFromCurrentWorkingDirectory();
    }
}
