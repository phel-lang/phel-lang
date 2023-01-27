<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\AbstractFactory;
use Phel\Filesystem\Domain\FilesystemInterface;
use Phel\Filesystem\Infrastructure\FilesystemSingleton;

/**
 * @method FilesystemConfig getConfig()
 */
final class FilesystemFactory extends AbstractFactory
{
    public function createFilesystem(): FilesystemInterface
    {
        return new FilesystemSingleton(
            $this->getConfig()->shouldKeepGeneratedTempFiles(),
        );
    }
}
