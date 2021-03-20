<?php

declare(strict_types=1);

namespace Phel\Interop\FileCreator;

use RuntimeException;

final class FileSystemIo implements FileIoInterface
{
    public function createDirectory(string $directory): void
    {
        if (!mkdir($directory, $permissions = 0777, $recursive = true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }
    }

    public function filePutContents(string $filename, string $content): void
    {
        file_put_contents($filename, $content);
    }
}
