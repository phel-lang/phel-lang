<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain;

interface FilesystemInterface
{
    public function addFile(string $file): void;

    /**
     * @return list<string> List of removed files
     */
    public function clearAll(): array;
}
