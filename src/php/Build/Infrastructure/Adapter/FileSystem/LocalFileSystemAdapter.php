<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Adapter\FileSystem;

use Phel\Build\Domain\Port\FileSystem\FileSystemPort;
use RuntimeException;

use function sprintf;

final readonly class LocalFileSystemAdapter implements FileSystemPort
{
    public function read(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read file "%s".', $path));
        }

        return $contents;
    }

    public function write(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }

    public function delete(string $path): void
    {
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function lastModified(string $path): int
    {
        $mtime = filemtime($path);

        if ($mtime === false) {
            throw new RuntimeException(sprintf('Unable to read file modification time for "%s".', $path));
        }

        return $mtime;
    }
}
