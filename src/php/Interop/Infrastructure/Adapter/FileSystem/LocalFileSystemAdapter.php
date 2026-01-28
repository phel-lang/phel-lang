<?php

declare(strict_types=1);

namespace Phel\Interop\Infrastructure\Adapter\FileSystem;

use Phel\Interop\Domain\Port\FileSystem\FileSystemPort;
use RuntimeException;

use function sprintf;

/**
 * Local file system adapter implementing FileSystemPort.
 */
final class LocalFileSystemAdapter implements FileSystemPort
{
    public function createDirectory(string $directory): void
    {
        if (mkdir($directory, 0777, true) || is_dir($directory)) {
            return;
        }

        if (is_dir($directory)) {
            return;
        }

        throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
    }

    public function filePutContents(string $filename, string $content): void
    {
        file_put_contents($filename, $content);
    }
}
