<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractConfig;
use Phel\Command\Domain\CodeDirectories;
use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;
use Phel\Shared\PhelProjectDirectory;

use function dirname;
use function sprintf;

final class CommandConfig extends AbstractConfig
{
    private const string DEFAULT_VENDOR_DIR = 'vendor';

    private const array DEFAULT_SRC_DIRS = ['src'];

    private const array DEFAULT_TEST_DIRS = ['tests'];

    private const string DEFAULT_OUTPUT_DIR = 'out';

    private const string DEFAULT_ERROR_LOG_FILE = 'phel-error.log';

    public function getCodeDirs(): CodeDirectories
    {
        $buildConfig = $this->get(PhelConfig::BUILD_CONFIG, []);

        // Point at the parent `src` directory (which contains `phel/`) so
        // phel's own core library is discoverable whether phel runs from its
        // own source tree, from a composer vendor dir, or from a PHAR.
        //
        // Use `dirname(..., 2)` rather than `__DIR__ . '/../..'` so the path
        // has no literal '..' segments: inside a PHAR the stream wrapper does
        // not normalize '..', which otherwise produces duplicate namespace
        // registrations when the same file is also reached via a clean path.
        //
        // The entry deliberately points one level above `src/phel` (so it
        // contains rather than is `src/phel`) so that entry-point detection
        // in `RunFacade::autoDetectEntryPoint` does not accidentally return
        // phel's own `core.phel` when the user has no entry point of their own.
        $phelInternalSrcDir = dirname(__DIR__, 2);

        return new CodeDirectories(
            $phelInternalSrcDir,
            (array) $this->get(PhelConfig::SRC_DIRS, self::DEFAULT_SRC_DIRS),
            (array) $this->get(PhelConfig::TEST_DIRS, self::DEFAULT_TEST_DIRS),
            (string) ($buildConfig[PhelBuildConfig::DEST_DIR] ?? self::DEFAULT_OUTPUT_DIR),
        );
    }

    public function getVendorDir(): string
    {
        return (string) $this->get(PhelConfig::VENDOR_DIR, self::DEFAULT_VENDOR_DIR);
    }

    public function getErrorLogFile(): string
    {
        $path = (string) $this->get(PhelConfig::ERROR_LOG_FILE, self::DEFAULT_ERROR_LOG_FILE);
        $phelDir = (string) $this->get(PhelConfig::PHEL_DIR, '');

        return PhelProjectDirectory::resolve($this->getAppRootDir(), $path, $phelDir);
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
        $outputDir = (string) ($buildConfig[PhelBuildConfig::DEST_DIR] ?? self::DEFAULT_OUTPUT_DIR);

        $cacheDir = (string) $this->get(PhelConfig::CACHE_DIR, '.phel/cache');
        $phelDir = (string) $this->get(PhelConfig::PHEL_DIR, '');
        $resolvedCacheDir = PhelProjectDirectory::resolve($this->getAppRootDir(), $cacheDir, $phelDir);

        return sprintf(
            'stale compiled output? try `rm -rf %s %s` and rebuild.',
            rtrim($outputDir, '/'),
            rtrim($resolvedCacheDir, '/'),
        );
    }
}
