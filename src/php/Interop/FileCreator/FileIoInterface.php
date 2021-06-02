<?php

declare(strict_types=1);

namespace Phel\Interop\FileCreator;

interface FileIoInterface
{
    public function createDirectory(string $directory): void;

    public function filePutContents(string $filename, string $content): void;
}
