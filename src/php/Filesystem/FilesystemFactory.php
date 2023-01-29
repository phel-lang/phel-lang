<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractFactory;
use Phel\Filesystem\Domain\FilesystemInterface;
use Phel\Filesystem\Domain\NullFilesystem;
use Phel\Filesystem\Infrastructure\RealFilesystem;

/**
 * @method FilesystemConfig getConfig()
 */
final class FilesystemFactory extends AbstractFactory
{
    public function createFilesystem(): FilesystemInterface
    {
        if ($this->getConfig()->shouldKeepGeneratedTempFiles()) {
            return new NullFilesystem();
        }

        return new RealFilesystem();
    }
}
