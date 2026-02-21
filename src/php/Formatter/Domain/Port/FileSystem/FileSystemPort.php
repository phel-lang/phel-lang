<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Port\FileSystem;

use Phel\Formatter\Domain\Exception\FilePathException;

/**
 * Driven port (secondary port) for file system operations in formatting.
 */
interface FileSystemPort
{
    /**
     * Validates that the file path is valid and accessible.
     *
     * @throws FilePathException
     */
    public function checkIfValid(string $filename): void;

    /**
     * Reads the contents of a file.
     */
    public function getContents(string $filename): string;

    /**
     * Writes contents to a file.
     */
    public function putContents(string $filename, string $data): void;
}
