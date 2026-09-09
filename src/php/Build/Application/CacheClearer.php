<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Gacela\Framework\Cache\FileCache;
use Phel\Shared\Performance\OpcacheFileCache;
use Phel\Shared\Performance\OpcacheFileCachePruner;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;

/**
 * @internal
 */
final readonly class CacheClearer
{
    public function __construct(
        private string $tempDir,
        private string $cacheDir,
        private string $opcacheDir,
    ) {}

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

        // Emptied in place, never removed: PHP aborts at startup when
        // opcache.file_cache points at a path that does not exist (#3268).
        $opcachePruner = new OpcacheFileCachePruner(new OpcacheFileCache($this->opcacheDir));
        if ($opcachePruner->clearContents()) {
            $clearedPaths[] = $this->opcacheDir;
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
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            // A symlinked directory is yielded but never descended into, so it
            // has to be unlinked: rmdir cannot remove a link.
            if ($file->isDir() && !$file->isLink()) {
                @rmdir($file->getPathname());
            } else {
                FileCache::delete($file->getPathname());
            }
        }

        @rmdir($dir);

        return true;
    }
}
