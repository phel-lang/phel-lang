<?php

declare(strict_types=1);

namespace Phel\Interop\Infrastructure\Io;

use Phel\Interop\Domain\FileCreator\FileIoInterface;
use RuntimeException;

use function sprintf;

final class FileSystemIo implements FileIoInterface
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
