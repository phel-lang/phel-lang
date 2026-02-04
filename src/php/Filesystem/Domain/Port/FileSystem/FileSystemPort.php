<?php

declare(strict_types=1);

namespace Phel\Filesystem\Domain\Port\FileSystem;

/**
 * Driven port (secondary port) for file tracking operations.
 */
interface FileSystemPort
{
    /**
     * Adds a file to track for cleanup.
     */
    public function addFile(string $file): void;

    /**
     * Clears all tracked files.
     */
    public function clearAll(): void;
}
