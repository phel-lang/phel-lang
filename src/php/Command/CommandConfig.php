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

    private const DEFAULT_VENDOR_DIR = 'vendor';
    private const DEFAULT_SRC_DIRS = ['src'];
    private const DEFAULT_TEST_DIRS = ['tests'];

    private static ?PhelOutConfig $outConfig = null;

    public function getCodeDirs(): CodeDirectories
    {
        return new CodeDirectories(
            (array)$this->get(self::SRC_DIRS, self::DEFAULT_SRC_DIRS),
            (array)$this->get(self::TEST_DIRS, self::DEFAULT_TEST_DIRS),
            $this->getOut()->getDestDir(),
        );
    }

    public function getVendorDir(): string
    {
        return (string)$this->get(self::VENDOR_DIR, self::DEFAULT_VENDOR_DIR);
    }

    public function getMainPhelNamespace(): string
    {
        return $this->getOut()->getMainPhelNamespace();
    }

    public function getOutputMainPhpPath(): string
    {
        return $this->getOut()->getMainPhpPath();
    }

    public function getOut(): PhelOutConfig
    {
        return self::$outConfig ??= PhelOutConfig::fromArray((array)$this->get('out', []));
    }
}
