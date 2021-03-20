<?php

declare(strict_types=1);

namespace Phel\Interop\FileCreator;

interface FileIoInterface
{
    public function createDirectory(string $dir): void;

    public function filePutContents(string $filename, string $content): void;
}
