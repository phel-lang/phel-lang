<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function is_dir;

final readonly class CacheClearer
{
    public function __construct(
        private string $tempDir,
        private string $cacheDir,
    ) {
    }

    /**
     * @return list<string> List of cleared paths
     */
    public function clearAll(): array
    {
        $clearedPaths = [];

        if ($this->deleteDirectory($this->tempDir)) {
            $clearedPaths[] = $this->tempDir;
        }

        if ($this->deleteDirectory($this->cacheDir)) {
            $clearedPaths[] = $this->cacheDir;
        }

        return $clearedPaths;
    }

    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($dir);

        return true;
    }
}
