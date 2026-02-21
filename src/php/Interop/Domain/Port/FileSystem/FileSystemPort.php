<?php

declare(strict_types=1);

namespace Phel\Interop\Domain\Port\FileSystem;

/**
 * Driven port (secondary port) for file system operations in interop.
 */
interface FileSystemPort
{
    /**
     * Creates a directory (recursively if needed).
     */
    public function createDirectory(string $directory): void;

    /**
     * Writes content to a file.
     */
    public function filePutContents(string $filename, string $content): void;
}
