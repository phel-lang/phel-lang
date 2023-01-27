<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

final class FakeFilesystem implements FilesystemInterface
{
    public function addFile(string $file): void
    {
    }

    public function clearAll(): array
    {
        return [];
    }
}
