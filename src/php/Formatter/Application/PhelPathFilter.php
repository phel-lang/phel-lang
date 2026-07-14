<?php

declare(strict_types=1);

namespace Phel\Formatter\Application;

use FilesystemIterator;
use Phel\Formatter\Domain\PathFilterInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PhelPathFilter implements PathFilterInterface
{
    private const string PHEL_EXTENSION = 'phel';

    /**
     * @param list<string> $paths
     *
     * @return list<string> The recursively unique valid paths to be formatted
     */
    public function filterPaths(array $paths): array
    {
        $returnPaths = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                if ($this->hasPhelExtension($path)) {
                    $returnPaths[] = $path;
                }
            } elseif (is_dir($path)) {
                foreach ($this->createRecursiveIterator($path) as $fileInfo) {
                    if (!$fileInfo instanceof SplFileInfo) {
                        continue;
                    }

                    if ($this->isPhelFile($fileInfo->getPathname())) {
                        $returnPaths[] = $fileInfo->getPathname();
                    }
                }
            }
        }

        return array_values(array_unique($returnPaths));
    }

    private function hasPhelExtension(string $path): bool
    {
        return pathinfo($path, PATHINFO_EXTENSION) === self::PHEL_EXTENSION;
    }

    /**
     * @return RecursiveIteratorIterator<RecursiveDirectoryIterator>
     */
    private function createRecursiveIterator(string $path): RecursiveIteratorIterator
    {
        // CATCH_GET_CHILD skips unreadable subdirectories instead of aborting
        // the whole format run with an uncaught UnexpectedValueException.
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD,
        );
    }

    private function isPhelFile(string $path): bool
    {
        return is_file($path)
            && $this->hasPhelExtension($path);
    }
}
