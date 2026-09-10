<?php

declare(strict_types=1);

namespace Phel\Shared\Performance;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

use function file_exists;
use function is_dir;
use function is_link;
use function rmdir;
use function str_ends_with;
use function unlink;

/**
 * Reclaims space in an OPcache file cache Phel owns.
 *
 * Two kinds of dead weight accumulate there and nothing else removes them
 * (#3268): whole subtrees written under a system id that a PHP upgrade or an
 * ini change retired, and `.bin` files for sources that no longer exist. The
 * `.bin` of a deleted file cannot be dropped through `opcache_invalidate()`:
 * under `opcache.file_cache_only=1`, which is how the CLI runs, the call
 * returns before it ever reaches the file cache, so the file has to go by hand.
 *
 * Every deletion stays inside the cache directory: a symlinked directory is
 * unlinked rather than descended into.
 */
final readonly class OpcacheFileCachePruner
{
    public function __construct(
        private OpcacheFileCache $fileCache,
    ) {}

    /**
     * Removes the subtrees belonging to any other system id.
     *
     * @return list<string> the removed entry paths
     */
    public function pruneForeignSystemIds(string $currentSystemId): array
    {
        if ($currentSystemId === '') {
            return [];
        }

        $removed = [];
        foreach ($this->fileCache->foreignEntries($currentSystemId) as $entry) {
            $this->remove($entry);
            $removed[] = $entry;
        }

        return $removed;
    }

    /**
     * Removes the `.bin` files under `<systemId><sourceRoot>` whose source is
     * gone, then the directories that leaves empty.
     *
     * @return list<string> the removed bin paths
     */
    public function pruneOrphanedBins(string $systemId, string $sourceRoot): array
    {
        $subtree = $this->fileCache->subtreePath($systemId, $sourceRoot);
        if ($systemId === '' || !is_dir($subtree)) {
            return [];
        }

        $removed = [];
        foreach ($this->walk($subtree) as $file) {
            $path = $file->getPathname();

            if ($file->isDir() && !$file->isLink()) {
                @rmdir($path);
                continue;
            }

            if (!str_ends_with($path, OpcacheFileCache::BIN_SUFFIX)) {
                continue;
            }

            $source = $this->fileCache->sourcePathOfBin($systemId, $path);
            if ($source !== '' && !file_exists($source)) {
                @unlink($path);
                $removed[] = $path;
            }
        }

        @rmdir($subtree);

        return $removed;
    }

    /**
     * Empties the cache directory without removing it: PHP aborts at startup
     * when `opcache.file_cache` points at a path that does not exist.
     */
    public function clearContents(): bool
    {
        if (!$this->fileCache->exists()) {
            return false;
        }

        foreach ($this->fileCache->entries() as $entry) {
            $this->remove($this->fileCache->path() . '/' . $entry);
        }

        return true;
    }

    private function remove(string $path): void
    {
        if (is_link($path) || !is_dir($path)) {
            @unlink($path);

            return;
        }

        foreach ($this->walk($path) as $file) {
            if ($file->isDir() && !$file->isLink()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($path);
    }

    /**
     * Children before parents, so a directory is only visited once emptied.
     * `RecursiveDirectoryIterator` does not descend into symlinked directories
     * unless asked to, which is what keeps a deletion inside this tree.
     *
     * A directory that disappears while the walk runs is not an error: the
     * cache is shared, so a second `phel` process pruning the same tree, or a
     * `cache:clear` next to a test run, removes entries under this one's feet.
     * `CATCH_GET_CHILD` absorbs that for a subdirectory and the catch does it
     * for the root. Without them the walk throws `UnexpectedValueException`,
     * which nothing along `bin/phel` handles, so pruning a cache someone else
     * is also pruning killed the command outright.
     *
     * @return iterable<SplFileInfo>
     */
    private function walk(string $directory): iterable
    {
        try {
            /** @var iterable<SplFileInfo> $iterator */
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD,
            );
        } catch (UnexpectedValueException) {
            return [];
        }

        return $iterator;
    }
}
