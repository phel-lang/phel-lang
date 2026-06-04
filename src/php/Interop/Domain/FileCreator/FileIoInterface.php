<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\FileCreator;

use RuntimeException;

interface FileIoInterface
{
    /**
     * Creates the directory (including missing parents); a no-op if it already exists.
     *
     * @throws RuntimeException if the directory could not be created
     */
    public function createDirectory(string $directory): void;

    public function filePutContents(string $filename, string $content): void;
}
