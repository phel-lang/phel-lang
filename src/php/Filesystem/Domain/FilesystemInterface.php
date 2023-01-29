<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

interface FilesystemInterface
{
    public function addFile(string $file): void;

    public function clearAll(): void;
}
