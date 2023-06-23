<?php

declare(strict_types=1);

namespace Phel\Command;

use Gacela\Framework\AbstractConfig;
use Phel\Command\Domain\CodeDirectories;
use Phel\Config\PhelOutConfig;

final class CommandConfig extends AbstractConfig
{
    public const SRC_DIRS = 'src-dirs';
    public const TEST_DIRS = 'test-dirs';
    public const VENDOR_DIR = 'vendor-dir';
    public const OUTPUT = 'out';
    public const ERROR_LOG_FILE = 'error-log-file';

    private const DEFAULT_VENDOR_DIR = 'vendor';
    private const DEFAULT_SRC_DIRS = ['src'];
    private const DEFAULT_TEST_DIRS = ['tests'];
    private const DEFAULT_OUTPUT_DIR = 'out';
    private const DEFAULT_ERROR_LOG_FILE = 'data/error.log';

    public function getCodeDirs(): CodeDirectories
    {
        $out = $this->get(self::OUTPUT, []);

        return new CodeDirectories(
            (array)$this->get(self::SRC_DIRS, self::DEFAULT_SRC_DIRS),
            (array)$this->get(self::TEST_DIRS, self::DEFAULT_TEST_DIRS),
            (string)($out[PhelOutConfig::DEST_DIR] ?? self::DEFAULT_OUTPUT_DIR),
        );
    }

    public function getVendorDir(): string
    {
        return (string)$this->get(self::VENDOR_DIR, self::DEFAULT_VENDOR_DIR);
    }

    public function getErrorLogFile(): string
    {
        return (string)$this->get(self::ERROR_LOG_FILE, self::DEFAULT_ERROR_LOG_FILE);
    }
}
