<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

final class NullFilesystem implements FilesystemInterface
{
    public function addFile(string $file): void
    {
    }

    public function clearAll(): void
    {
    }
}
