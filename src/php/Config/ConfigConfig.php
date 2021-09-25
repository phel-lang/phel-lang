<?php

declare(strict_types=1);

namespace Phel\Config;

use Gacela\Framework\Config;
use Phel\AbstractPhelConfig;

final class ConfigConfig extends AbstractPhelConfig
{
    public const SRC_DIRS = 'src-dirs';
    public const TEST_DIRS = 'test-dirs';
    public const VENDOR_DIR = 'vendor-dir';

    public function getSourceDirectories(): array
    {
        return (array)$this->get(self::SRC_DIRS);
    }

    public function getTestDirectories(): array
    {
        return (array)$this->get(self::TEST_DIRS);
    }

    public function getVendorDir(): string
    {
        return (string)$this->get(self::VENDOR_DIR);
    }

    public function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }
}
