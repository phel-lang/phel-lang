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

    public const string OPTIMIZATION_LEVEL = 'optimization-level';

    /** @var list<string> */
    public const array DEFAULT_SRC_DIRS = ['src'];

    private const string PHEL_TEMP_SUBDIR = '/phel';

    private const string DEFAULT_CACHE_DIR = '.phel/cache';

    public string $tempDir;

    public string $cacheDir;

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
        string $cacheDir = self::DEFAULT_CACHE_DIR,
        public array $formatDirs = ['src', 'tests'],
        public bool $enableAsserts = true,
        public bool $warnDeprecations = false,
        public bool $enableNamespaceCache = true,
        public bool $enableCompiledCodeCache = true,
        public string $phelDir = '',
        public int $optimizationLevel = 0,
    ) {
        $this->tempDir = $tempDir === null
            ? sys_get_temp_dir() . self::PHEL_TEMP_SUBDIR . '/tmp'
            : rtrim($tempDir, DIRECTORY_SEPARATOR);
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR);
    }

    /**
     * Quick factory for typical project setup. Defaults to Flat layout (src/, tests/).
     *
     * Examples:
     *   return PhelConfig::forProject();                           // zero-config, Flat layout
     *   return PhelConfig::forProject(ProjectLayout::Root);        // single-file / scratch project
     *   return PhelConfig::forProject(ProjectLayout::Nested, 'my-app\main');
     *   return PhelConfig::forProject(ProjectLayout::Nested)
     *       ->withMainPhelNamespace('my-app\main');
     */
    public static function forProject(
        ProjectLayout $layout = ProjectLayout::Flat,
        string $mainNamespace = '',
    ): self {
        $config = new self()->withLayout($layout);

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

    /**
     * Compiler optimization level: 0 = off (default), 1 = reserved for
     * auto-inlining, 2 = `^:pure` call-site inlining + self-recursive
     * tail-call rewriting. See `CompileOptions::DEFAULT_OPTIMIZATION_LEVEL`.
     */
    public function getOptimizationLevel(): int
    {
        return $this->optimizationLevel;
    }

    // ========================================
    // Layout
    // ========================================

    /**
     * Apply a project layout (nested or flat). Returns a new instance with src,
     * test, format, and export-from directories fully reset to the layout's
     * defaults. This is a complete directory reconfiguration, not a partial
     * patch: any previously customised directory in those four groups is
     * overwritten. Non-directory config (build, export prefix, caching, flags)
     * is left untouched.
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
     * Directories scanned for Phel source namespaces (relative to the project
     * root). Used by `run`, `test`, `build`, and namespace resolution.
     * Default: `['src']`.
     *
     * @param list<string> $list
     */
    public function withSrcDirs(array $list): self
    {
        return $this->with(['srcDirs' => $list]);
    }

    /**
     * Directories scanned for test namespaces by `phel test`.
     * Default: `['tests']`.
     *
     * @param list<string> $list
     */
    public function withTestDirs(array $list): self
    {
        return $this->with(['testDirs' => $list]);
    }

    /**
     * Composer vendor directory, used to locate Phel dependencies. Must be
     * relative to the project root. Default: `'vendor'`.
     */
    public function withVendorDir(string $dir): self
    {
        return $this->with(['vendorDir' => $dir]);
    }

    /**
     * File that runtime/compile errors are appended to. Default:
     * `'.phel/error.log'`.
     */
    public function withErrorLogFile(string $filepath): self
    {
        return $this->with(['errorLogFile' => $filepath]);
    }

    /**
     * Configure the build value object.
     *
     * Pass a `PhelBuildConfig` to replace it wholesale (this overwrites anything
     * set via the flattened build withers, so call it first). Or pass a
     * configurator closure to patch the current build config in place, which
     * composes cleanly with the flattened withers regardless of call order:
     *
     *     ->withBuildConfig(fn (PhelBuildConfig $b) => $b->withDestDir('dist'))
     *
     * @param callable(PhelBuildConfig):PhelBuildConfig|PhelBuildConfig $buildConfig
     */
    public function withBuildConfig(PhelBuildConfig|callable $buildConfig): self
    {
        $resolved = $buildConfig instanceof PhelBuildConfig
            ? $buildConfig
            : $buildConfig($this->buildConfig);

        return $this->with(['buildConfig' => $resolved]);
    }

    /**
     * Configure the export value object.
     *
     * Pass a `PhelExportConfig` to replace it wholesale (this overwrites anything
     * set via the flattened export withers and resets unspecified fields to their
     * defaults, so call it first). Or pass a configurator closure to patch the
     * current export config in place, which composes with the flattened withers
     * regardless of call order:
     *
     *     ->withExportConfig(fn (PhelExportConfig $e) => $e->withNamespacePrefix('App'))
     *
     * @param callable(PhelExportConfig):PhelExportConfig|PhelExportConfig $exportConfig
     */
    public function withExportConfig(PhelExportConfig|callable $exportConfig): self
    {
        $resolved = $exportConfig instanceof PhelExportConfig
            ? $exportConfig
            : $exportConfig($this->exportConfig);

        return $this->with(['exportConfig' => $resolved]);
    }

    /**
     * Flattens the build namespace onto PhelConfig.
     *
     * Only the namespace is set here; the entry-point PHP path is left to
     * PhelBuildConfig's lazy derivation (`<destDir>/index.php`). This keeps the
     * build withers order-independent: `withMainPhelNamespace()->withBuildDestDir('dist')`
     * and the reverse both yield `dist/index.php`. Zero-config projects still
     * derive `out/index.php` from the default dest dir.
     */
    public function withMainPhelNamespace(string $namespace): self
    {
        return $this->with(['buildConfig' => $this->buildConfig->withMainPhelNamespace($namespace)]);
    }

    /**
     * Entry-point PHP file written by `phel build`. A value containing `/` is
     * used verbatim; a bare filename is placed under the build dest dir; a `.php`
     * extension is appended if missing. Leave unset to derive `<destDir>/index.php`.
     */
    public function withMainPhpPath(string $path): self
    {
        return $this->with(['buildConfig' => $this->buildConfig->withMainPhpPath($path)]);
    }

    /**
     * Output directory for compiled PHP written by `phel build`.
     * Default: `'out'`.
     */
    public function withBuildDestDir(string $dir): self
    {
        return $this->with(['buildConfig' => $this->buildConfig->withDestDir($dir)]);
    }

    /**
     * PHP namespace prefix prepended to namespaces generated by `phel export`.
     * Default: `'PhelGenerated'`.
     */
    public function withExportNamespacePrefix(string $prefix): self
    {
        return $this->with(['exportConfig' => $this->exportConfig->withNamespacePrefix($prefix)]);
    }

    /**
     * Directory where `phel export` writes the generated PHP wrappers.
     * Default: `'src/PhelGenerated'`.
     */
    public function withExportTargetDirectory(string $dir): self
    {
        return $this->with(['exportConfig' => $this->exportConfig->withTargetDirectory($dir)]);
    }

    /**
     * Source directories scanned by `phel export` for `^{:export true}` fns.
     * Default: `['src']`.
     *
     * @param list<string> $dirs
     */
    public function withExportFromDirectories(array $dirs): self
    {
        return $this->with(['exportConfig' => $this->exportConfig->withFromDirectories($dirs)]);
    }

    /**
     * Skip files during `phel build`. Each entry is matched as a substring
     * against the source file path; a matching file is not compiled and not
     * included in the output. Default: `[]`.
     *
     * @param list<string> $list
     */
    public function withIgnoreWhenBuilding(array $list): self
    {
        return $this->with(['ignoreWhenBuilding' => $list]);
    }

    /**
     * Bypass the incremental cache for selected files during `phel build`. Each
     * entry is matched as a substring against the compiled output path; a
     * matching file is recompiled on every build. Default: `[]`.
     *
     * @param list<string> $list
     */
    public function withNoCacheWhenBuilding(array $list): self
    {
        return $this->with(['noCacheWhenBuilding' => $list]);
    }

    /**
     * Keep intermediate compilation artifacts in the temp dir instead of
     * cleaning them up (useful for debugging the compiler). Default: `false`.
     */
    public function withKeepGeneratedTempFiles(bool $flag = true): self
    {
        return $this->with(['keepGeneratedTempFiles' => $flag]);
    }

    /**
     * Directory for transient compilation artifacts (e.g. `(load ...)` output).
     * Default: the system temp dir under `/phel/tmp`. Distinct from the cache
     * dir, which holds persistent caches.
     */
    public function withTempDir(string $dir): self
    {
        return $this->with(['tempDir' => rtrim($dir, DIRECTORY_SEPARATOR)]);
    }

    /**
     * Directory for persistent caches (namespace + compiled-code). Relative to
     * the project root; the `PHEL_CACHE_DIR` env var overrides it.
     * Default: `'.phel/cache'`.
     */
    public function withCacheDir(string $dir): self
    {
        // Trailing separators are normalized by the constructor.
        return $this->with(['cacheDir' => $dir]);
    }

    /**
     * Directories scanned by `phel format`. Default: `['src', 'tests']`.
     *
     * @param list<string> $list
     */
    public function withFormatDirs(array $list): self
    {
        return $this->with(['formatDirs' => $list]);
    }

    /**
     * Compile and run `(assert ...)` forms. When disabled, assertion code is
     * stripped from the compiled output. Default: `true`.
     */
    public function withEnableAsserts(bool $flag = true): self
    {
        return $this->with(['enableAsserts' => $flag]);
    }

    /**
     * Emit compiler warnings when deprecated symbols/forms are used.
     * Default: `false`.
     */
    public function withWarnDeprecations(bool $flag = true): self
    {
        return $this->with(['warnDeprecations' => $flag]);
    }

    /**
     * Cache parsed namespace metadata (file-to-namespace mapping and load
     * dependencies) in `<cacheDir>/namespace-cache.php`, so builds skip
     * re-scanning `(ns ...)` forms. Distinct from the compiled-code cache.
     * Default: `true`.
     */
    public function withEnableNamespaceCache(bool $flag = true): self
    {
        return $this->with(['enableNamespaceCache' => $flag]);
    }

    /**
     * Cache the compiled PHP output per source file (keyed by content hash) in
     * `<cacheDir>/compiled/`, so unchanged files are not recompiled. Invalidated
     * when the source or optimization level changes. Default: `true`.
     */
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

    /**
     * Set the compiler optimization level applied by `phel build`, `phel run`,
     * and `phel test` (REPL and nREPL always stay at 0). Negative values are
     * clamped to 0. Level 2 enables `^:pure` call-site inlining and
     * self-recursive tail-call rewriting.
     */
    public function withOptimizationLevel(int $level): self
    {
        return $this->with(['optimizationLevel' => max(0, $level)]);
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
        return new PhelConfigValidator()->validate($this);
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
            self::OPTIMIZATION_LEVEL => $this->optimizationLevel,
        ];
    }

    /**
     * Internal builder: returns a new instance with the supplied fields
     * overridden. Each public `with*()` method delegates to this, allowing
     * single-line immutable updates.
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
            'optimizationLevel' => $this->optimizationLevel,
        ];

        return new self(...[...$base, ...$overrides]);
    }

}
