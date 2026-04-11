<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractConfig;
use Phel\Command\Domain\CodeDirectories;
use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

use function dirname;

final class CommandConfig extends AbstractConfig
{
    private const string DEFAULT_VENDOR_DIR = 'vendor';

    private const array DEFAULT_SRC_DIRS = ['src/phel'];

    private const array DEFAULT_TEST_DIRS = ['tests/phel'];

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
            [$phelInternalSrcDir, ...(array) $this->get(PhelConfig::SRC_DIRS, self::DEFAULT_SRC_DIRS)],
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
        return (string) $this->get(PhelConfig::ERROR_LOG_FILE, self::DEFAULT_ERROR_LOG_FILE);
    }
}
