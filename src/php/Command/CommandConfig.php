<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractConfig;
use Phel\Command\Domain\CodeDirectories;
use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;
use Phel\Shared\PhelProjectDirectory;
use Phel\Shared\ScalarCoercion;

use function dirname;
use function is_array;
use function sprintf;
use function strlen;

/**
 * @internal
 */
final class CommandConfig extends AbstractConfig
{
    private const string DEFAULT_VENDOR_DIR = 'vendor';

    private const array DEFAULT_SRC_DIRS = ['src'];

    private const array DEFAULT_TEST_DIRS = ['tests'];

    private const string DEFAULT_OUTPUT_DIR = 'out';

    private const string DEFAULT_CACHE_DIR = '.phel/cache';

    private const string DEFAULT_ERROR_LOG_FILE = 'phel-error.log';

    public function getCodeDirs(): CodeDirectories
    {
        $buildConfig = $this->get(PhelConfig::BUILD_CONFIG, []);
        $buildConfig = is_array($buildConfig) ? $buildConfig : [];

        return new CodeDirectories(
            $this->getPhelInternalSrcDir(),
            ScalarCoercion::toStringList($this->get(PhelConfig::SRC_DIRS, self::DEFAULT_SRC_DIRS), self::DEFAULT_SRC_DIRS),
            ScalarCoercion::toStringList($this->get(PhelConfig::TEST_DIRS, self::DEFAULT_TEST_DIRS), self::DEFAULT_TEST_DIRS),
            ScalarCoercion::toString($buildConfig[PhelBuildConfig::DEST_DIR] ?? null, self::DEFAULT_OUTPUT_DIR),
        );
    }

    /**
     * Phel's own `src` directory (which contains `phel/`), so phel's core
     * library is discoverable whether phel runs from its own source tree, from
     * a composer vendor dir, or from a PHAR.
     *
     * Use `dirname(..., 2)` rather than `__DIR__ . '/../..'` so the path has no
     * literal '..' segments: inside a PHAR the stream wrapper does not
     * normalize '..', which otherwise produces duplicate namespace
     * registrations when the same file is also reached via a clean path.
     *
     * It deliberately points one level above `src/phel` (so it contains rather
     * than is `src/phel`) so that entry-point detection in
     * `RunFacade::autoDetectEntryPoint` does not accidentally return phel's own
     * `core.phel` when the user has no entry point of their own.
     */
    public function getPhelInternalSrcDir(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Shares `PhelProjectDirectory::resolveCacheDir()` with the build and
     * compiler configs, so an error report names the same cache directory the
     * compiled artifacts are written to.
     */
    public function getCacheDir(): string
    {
        return PhelProjectDirectory::resolveCacheDir(
            $this->getAppRootDir(),
            ScalarCoercion::toString($this->get(PhelConfig::CACHE_DIR, self::DEFAULT_CACHE_DIR), self::DEFAULT_CACHE_DIR),
            ScalarCoercion::toString($this->get(PhelConfig::PHEL_DIR, '')),
        );
    }

    public function getVendorDir(): string
    {
        return ScalarCoercion::toString($this->get(PhelConfig::VENDOR_DIR, self::DEFAULT_VENDOR_DIR), self::DEFAULT_VENDOR_DIR);
    }

    public function getErrorLogFile(): string
    {
        $path = ScalarCoercion::toString($this->get(PhelConfig::ERROR_LOG_FILE, self::DEFAULT_ERROR_LOG_FILE), self::DEFAULT_ERROR_LOG_FILE);
        $phelDir = ScalarCoercion::toString($this->get(PhelConfig::PHEL_DIR, ''));

        return PhelProjectDirectory::resolve($this->getAppRootDir(), $path, $phelDir);
    }

    /**
     * The way out of a collapsed trace, appended to the `... N internal frames`
     * marker: the flag that expands it, and the log that always holds the full
     * one. Built from the configured log path so it names the project's real
     * layout (`withErrorLogFile()`, `withPhelDir()`).
     */
    public function getCollapsedTraceHint(): string
    {
        return sprintf(
            '--stack-trace to show, full trace in %s',
            $this->getErrorLogFileForDisplay(),
        );
    }

    /**
     * Recipe for clearing build state when compiled output is corrupted.
     * Uses the configured output and state directories so the hint reflects
     * the project's actual layout (`withBuildDestDir()`, `withCacheDir()`,
     * `withPhelDir()`).
     */
    public function getStaleOutputHint(): string
    {
        $buildConfig = $this->get(PhelConfig::BUILD_CONFIG, []);
        $buildConfig = is_array($buildConfig) ? $buildConfig : [];

        $outputDir = ScalarCoercion::toString($buildConfig[PhelBuildConfig::DEST_DIR] ?? null, self::DEFAULT_OUTPUT_DIR);

        return sprintf(
            'stale compiled output? try `rm -rf %s %s` and rebuild.',
            rtrim($outputDir, '/'),
            rtrim($this->getCacheDir(), '/'),
        );
    }

    /**
     * The log path shortened to how the user would type it, so a one-line
     * marker does not carry an absolute path across the terminal.
     */
    private function getErrorLogFileForDisplay(): string
    {
        $file = $this->getErrorLogFile();
        $root = rtrim($this->getAppRootDir(), '/\\') . DIRECTORY_SEPARATOR;

        return str_starts_with($file, $root)
            ? substr($file, strlen($root))
            : $file;
    }
}
