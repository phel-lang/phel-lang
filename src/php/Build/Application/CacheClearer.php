<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Gacela\Framework\Cache\FileCache;
use InvalidArgumentException;
use Phel\Shared\Performance\OpcacheFileCache;
use Phel\Shared\Performance\OpcacheFileCachePruner;
use Phel\Shared\ProjectRootResolver;
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
        private ?string $projectRoot = null,
    ) {}

    /**
     * @return list<string> List of cleared paths
     */
    public function clearAll(): array
    {
        foreach ([$this->tempDir, $this->cacheDir, $this->opcacheDir] as $dir) {
            $this->assertSafeDirectory($dir);
        }

        $clearedPaths = [];

        if ($this->deleteDirectory($this->tempDir)) {
            $clearedPaths[] = $this->tempDir;
        }

        if ($this->deleteDirectory($this->cacheDir)) {
            $clearedPaths[] = $this->cacheDir;
        }

        // Emptied in place, never removed: PHP aborts at startup when
        // opcache.file_cache points at a path that does not exist (#3268).
        // A symlinked root is left alone: emptying it would empty its target,
        // and unlinking it would leave opcache.file_cache pointing nowhere.
        $opcachePruner = new OpcacheFileCachePruner(new OpcacheFileCache($this->opcacheDir));
        if (!is_link($this->opcacheDir) && $opcachePruner->clearContents()) {
            $clearedPaths[] = $this->opcacheDir;
        }

        return $clearedPaths;
    }

    private function assertSafeDirectory(string $dir): void
    {
        $this->assertNotFilesystemRoot($dir);
        if (is_link($dir) || !is_dir($dir)) {
            return;
        }

        $path = realpath($dir);
        if ($path === false) {
            throw new InvalidArgumentException('Cannot resolve cache directory: ' . $dir);
        }

        $this->assertNotFilesystemRoot($path);

        $cwd = getcwd();
        $protectedPaths = [
            DIRECTORY_SEPARATOR,
            getenv('HOME') ?: getenv('USERPROFILE'),
            $cwd,
            $this->projectRoot ?? ($cwd === false ? false : ProjectRootResolver::resolveFromCwd($cwd)),
        ];
        foreach ($protectedPaths as $protected) {
            $protectedPath = $protected === false ? false : realpath($protected);
            if ($protectedPath === false) {
                continue;
            }

            if (str_starts_with($this->comparisonPath($protectedPath), $this->comparisonPath($path))) {
                throw new InvalidArgumentException('Refusing to clear protected directory: ' . $dir);
            }
        }

        if (is_file($path . '/phel-config.php') || file_exists($path . '/.git')) {
            throw new InvalidArgumentException('Refusing to clear project directory: ' . $dir);
        }
    }

    private function assertNotFilesystemRoot(string $dir): void
    {
        $path = str_replace('\\', '/', $dir);
        if (str_starts_with(strtolower($path), '//?/unc/')) {
            $path = '//' . substr($path, 8);
        } elseif (str_starts_with($path, '//?/')) {
            $path = substr($path, 4);
        }

        $root = rtrim($path, '/');
        if (($path !== '' && $root === '')
            || preg_match('/^[a-z]:$/i', $root) === 1
            || preg_match('~^//[^/]+/[^/]+$~', $root) === 1
        ) {
            throw new InvalidArgumentException('Refusing to clear filesystem root: ' . $dir);
        }
    }

    private function comparisonPath(string $path): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower(str_replace('\\', '/', $path));
            if (str_starts_with($path, '//?/unc/')) {
                $path = '//' . substr($path, 8);
            } elseif (str_starts_with($path, '//?/')) {
                $path = substr($path, 4);
            }
        }

        return rtrim($path, '/') . '/';
    }

    private function deleteDirectory(string $dir): bool
    {
        // A configured dir that is a symlink loses the link only: following it
        // would empty whatever it points at, maybe another user's files.
        if (is_link($dir)) {
            return @unlink($dir);
        }

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
