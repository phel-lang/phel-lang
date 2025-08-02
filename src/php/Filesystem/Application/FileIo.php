<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Phel\Filesystem\Domain\FileIoInterface;

final class FileIo implements FileIoInterface
{
    public function isWritable(string $tempDir): bool
    {
        return is_writable($tempDir);
    }
}
