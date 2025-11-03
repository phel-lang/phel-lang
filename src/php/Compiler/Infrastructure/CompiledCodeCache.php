<?php

declare(strict_types=1);

namespace Phel\Compiler\Infrastructure;

use Phel\Filesystem\FilesystemFacadeInterface;
use RuntimeException;

use function dirname;
use function md5;
use function sprintf;

/**
 * Manages persistent caching of compiled PHP code.
 *
 * This class reduces compilation time by caching compiled PHP code to disk.
 * Cache files are stored in the configured temp directory (from PhelConfig)
 * under a `phel-compiled-cache/` subdirectory, using MD5 hashes of the
 * compiled code as keys.
 */
final readonly class CompiledCodeCache
{
    public const string CACHE_SUBDIR = 'phel-compiled-cache';

    public function __construct(
        private FilesystemFacadeInterface $filesystemFacade,
    ) {
    }

    /**
     * @return string|null The path to the cached file, or null if not cached
     */
    public function get(string $code): ?string
    {
        $cacheFile = $this->getCacheFilePath($code);

        if (!file_exists($cacheFile)) {
            return null;
        }

        return $cacheFile;
    }

    /**
     * @throws RuntimeException
     */
    public function store(string $code): string
    {
        $cacheFile = $this->getCacheFilePath($code);

        // Ensure cache directory exists
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir) && (!mkdir($cacheDir, 0755, true) && !is_dir($cacheDir))) {
            throw new RuntimeException(sprintf('Unable to create cache directory: %s', $cacheDir));
        }

        // Write code to cache file
        if (file_put_contents($cacheFile, $code) === false) {
            throw new RuntimeException(sprintf('Unable to write cache file: %s', $cacheFile));
        }

        return $cacheFile;
    }

    public function clear(): void
    {
        $cacheDir = $this->getCacheDirPath();
        if (is_dir($cacheDir)) {
            $this->recursiveRemoveDirectory($cacheDir);
        }
    }

    private function getCacheFilePath(string $code): string
    {
        $cacheDir = $this->getCacheDirPath();
        $hash = md5($code);

        return sprintf('%s/%s.php', $cacheDir, $hash);
    }

    private function getCacheDirPath(): string
    {
        $tempDir = $this->filesystemFacade->getTempDir();

        return $tempDir . '/' . self::CACHE_SUBDIR;
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.') {
                continue;
            }

            if ($file === '..') {
                continue;
            }

            $path = sprintf('%s/%s', $dir, $file);
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
