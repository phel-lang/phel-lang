<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Port\FileSystem;

interface FileSystemPort
{
    public function read(string $path): string;

    public function write(string $path, string $content): void;

    public function delete(string $path): void;

    public function exists(string $path): bool;

    public function lastModified(string $path): int;
}
