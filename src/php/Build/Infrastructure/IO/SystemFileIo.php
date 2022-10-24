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

    public function putContents(string $filename, string $content): void
    {
        file_put_contents($filename, $content);
    }

    public function removeFile(string $filename): void
    {
        if (file_exists($filename)) {
            @unlink($filename);
        }
    }
}
