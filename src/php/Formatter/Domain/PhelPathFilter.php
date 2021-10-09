<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class PhelPathFilter implements PathFilterInterface
{
    private const PHEL_EXTENSION = 'phel';

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
                    if ($this->isPhelFile($fileInfo->getPathname())) {
                        $returnPaths[] = $fileInfo->getPathname();
                    }
                }
            }
        }

        return array_unique($returnPaths);
    }

    private function hasPhelExtension(string $path): bool
    {
        return self::PHEL_EXTENSION === pathinfo($path, PATHINFO_EXTENSION);
    }

    private function createRecursiveIterator(string $path): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
    }

    private function isPhelFile(string $path): bool
    {
        return is_file($path)
            && $this->hasPhelExtension($path);
    }
}
