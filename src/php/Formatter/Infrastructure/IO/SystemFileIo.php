<?php

declare(strict_types=1);

namespace Phel\Formatter\Infrastructure\IO;

final class SystemFileIo implements FileIoInterface
{
    public function getContents(string $filename): string
    {
        return file_get_contents($filename);
    }

    public function putContents(string $filename, string $data): void
    {
        file_put_contents($filename, $data);
    }
}
