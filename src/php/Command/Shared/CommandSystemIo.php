<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

use RuntimeException;

final class CommandSystemIo implements CommandIoInterface
{
    public function createDirectory(string $directory): void
    {
        if (!mkdir($directory, $permissions = 0777, $recursive = true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }
    }

    public function fileGetContents(string $filename): string
    {
        return file_get_contents($filename);
    }

    public function filePutContents(string $filename, string $content): void
    {
        file_put_contents($filename, $content);
    }

    public function writeln(string $string = ''): void
    {
        print $string . PHP_EOL;
    }
}
