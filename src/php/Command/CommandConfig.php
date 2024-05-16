<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractConfig;
use Phel\Command\Domain\CodeDirectories;
use Phel\Config\PhelBuildConfig;
use Phel\Config\PhelConfig;

final class CommandConfig extends AbstractConfig
{
    private const DEFAULT_VENDOR_DIR = 'vendor';

    private const DEFAULT_SRC_DIRS = ['src'];

    private const DEFAULT_TEST_DIRS = ['tests'];

    private const DEFAULT_OUTPUT_DIR = 'out';

    private const DEFAULT_ERROR_LOG_FILE = 'data/error.log';

    public function getCodeDirs(): CodeDirectories
    {
        $buildConfig = $this->get(PhelConfig::BUILD_CONFIG, []);

        return new CodeDirectories(
            [__DIR__ . '/../../', ...(array)$this->get(PhelConfig::SRC_DIRS, self::DEFAULT_SRC_DIRS)],
            (array)$this->get(PhelConfig::TEST_DIRS, self::DEFAULT_TEST_DIRS),
            (string)($buildConfig[PhelBuildConfig::DEST_DIR] ?? self::DEFAULT_OUTPUT_DIR),
        );
    }

    public function getVendorDir(): string
    {
        return (string)$this->get(PhelConfig::VENDOR_DIR, self::DEFAULT_VENDOR_DIR);
    }

    public function getErrorLogFile(): string
    {
        return (string)$this->get(PhelConfig::ERROR_LOG_FILE, self::DEFAULT_ERROR_LOG_FILE);
    }
}
