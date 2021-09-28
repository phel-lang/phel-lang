<?php

declare(strict_types=1);

namespace Phel\Run;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\Config;
use Phel\Run\Domain\CodeDirectories;

final class RunConfig extends AbstractConfig
{
    public const SRC_DIRS = 'src-dirs';
    public const TEST_DIRS = 'test-dirs';
    public const VENDOR_DIR = 'vendor-dir';

    public function getPhelReplHistory(): string
    {
        return $this->getApplicationRootDir() . '/.phel-repl-history';
    }

    public function getReplStartupFile(): string
    {
        return __DIR__ . '/Repl/startup.phel';
    }

    public function getApplicationRootDir(): string
    {
        return Config::getInstance()->getApplicationRootDir();
    }

    public function getConfigDirectories(): CodeDirectories
    {
        return new CodeDirectories(
            (array)$this->get(self::SRC_DIRS),
            (array)$this->get(self::TEST_DIRS)
        );
    }

    public function getVendorDir(): string
    {
        return (string)$this->get(self::VENDOR_DIR);
    }
}
