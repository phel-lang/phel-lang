<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;

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
    private array $srcDirs = ['src/phel'];

    /** @var list<string> */
    private array $testDirs = ['tests/phel'];

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
    private array $formatDirs = ['src/phel', 'tests/phel'];

    private bool $enableAsserts = true;

    private bool $enableNamespaceCache = true;

    private bool $enableCompiledCodeCache = true;

    public function __construct()
    {
        $this->exportConfig = new PhelExportConfig();
        $this->buildConfig = new PhelBuildConfig();
        $this->tempDir = sys_get_temp_dir() . '/phel/tmp';
        $this->cacheDir = sys_get_temp_dir() . '/phel/cache';
    }

    /**
     * Quick factory for typical project setup with conventional layout.
     *
     * Example:
     *   return PhelConfig::forProject('my-app\core');
     */
    public static function forProject(string $mainNamespace): self
    {
        return (new self())
            ->setMainPhelNamespace($mainNamespace);
    }

    /**
     * Use conventional directory layout: src/phel and tests/phel.
     * This is the recommended structure for Phel projects.
     */
    public function useConventionalLayout(): self
    {
        $this->srcDirs = ['src/phel'];
        $this->testDirs = ['tests/phel'];
        $this->formatDirs = ['src/phel', 'tests/phel'];
        $this->exportConfig->setFromDirectories(['src/phel']);

        return $this;
    }

    /**
     * Use flat directory layout: src and tests (for simpler projects).
     */
    public function useFlatLayout(): self
    {
        $this->srcDirs = ['src'];
        $this->testDirs = ['tests'];
        $this->formatDirs = ['src', 'tests'];
        $this->exportConfig->setFromDirectories(['src']);

        return $this;
    }

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

    /**
     * @deprecated use `setBuildConfig(PhelBuildConfig)`
     */
    public function setOut(PhelBuildConfig $buildConfig): self
    {
        return $this->setBuildConfig($buildConfig);
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
}
