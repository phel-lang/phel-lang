<?php

declare(strict_types=1);

namespace Phel\Filesystem;

interface FilesystemFacadeInterface
{
    public function addFile(string $file): void;

    public function clearAll(): void;
}
