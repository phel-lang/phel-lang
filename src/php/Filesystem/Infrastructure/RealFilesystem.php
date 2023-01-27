<?php

declare(strict_types=1);

namespace Phel\Filesystem\Infrastructure;

use Phel\Filesystem\Domain\Files;
use Phel\Filesystem\Domain\FilesystemInterface;

final class RealFilesystem implements FilesystemInterface
{
    public static function reset(): void
    {
        Files::reset();
    }

    public function addFile(string $file): void
    {
        Files::addFile($file);
    }

    public function clearAll(): void
    {
        foreach (Files::readAll() as $file) {
            unlink($file);
        }
    }
}
