<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\IO;

use Phel\Build\Domain\IO\FileIoInterface;

final class SystemFileIo implements FileIoInterface
{
    public function getContents(string $filename): string
    {
        return file_get_contents($filename);
    }
}
