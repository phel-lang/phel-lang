<?php

declare(strict_types=1);

namespace Phel\Compiler\Infrastructure;

use Phel\Filesystem\FilesystemFacadeInterface;

use function is_array;

final readonly class CompilationCacheManager
{
    private const string CACHE_DIR = '.phel-cache';

    private const string CACHE_INDEX_FILE = 'cache-index.json';

    public function __construct(
        private FilesystemFacadeInterface $filesystemFacade,
        private string $projectRoot,
    ) {
    }

    /**
     * Gets the cache directory path, creating it if needed.
     */
    public function getCacheDirectory(): string
    {
        $cacheDir = $this->projectRoot . DIRECTORY_SEPARATOR . self::CACHE_DIR;

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        return $cacheDir;
    }

    /**
     * Calculates hash for a Phel source file.
     */
    public function calculateFileHash(string $phelFilePath): string
    {
        if (!file_exists($phelFilePath)) {
            return '';
        }

        $content = file_get_contents($phelFilePath);
        return hash('xxh128', $content ?: '');
    }

    /**
     * Gets cached PHP file path if cache is valid, null otherwise.
     */
    public function getCachedPhpPath(string $phelFilePath): ?string
    {
        $currentHash = $this->calculateFileHash($phelFilePath);
        if ($currentHash === '') {
            return null;
        }

        $cacheIndex = $this->loadCacheIndex();
        $normalizedPath = $this->normalizePath($phelFilePath);

        if (!isset($cacheIndex[$normalizedPath])) {
            return null;
        }

        $cacheEntry = $cacheIndex[$normalizedPath];

        // Check if hash matches
        if ($cacheEntry['hash'] !== $currentHash) {
            return null;
        }

        $cachedPhpPath = $this->getCacheDirectory() . DIRECTORY_SEPARATOR . $cacheEntry['phpFile'];

        // Verify the cached file exists
        if (!file_exists($cachedPhpPath)) {
            return null;
        }

        return $cachedPhpPath;
    }

    /**
     * Stores compiled PHP code in cache and returns the path.
     */
    public function storeCachedPhp(string $phelFilePath, string $phpCode): string
    {
        $hash = $this->calculateFileHash($phelFilePath);
        $phpFileName = $hash . '.php';
        $cachedPhpPath = $this->getCacheDirectory() . DIRECTORY_SEPARATOR . $phpFileName;

        // Write the compiled PHP file
        $fullPhpCode = "<?php\n" . $phpCode;
        file_put_contents($cachedPhpPath, $fullPhpCode);

        // Register in filesystem facade for cleanup
        $this->filesystemFacade->addFile($cachedPhpPath);

        // Update cache index
        $cacheIndex = $this->loadCacheIndex();
        $normalizedPath = $this->normalizePath($phelFilePath);

        $cacheIndex[$normalizedPath] = [
            'hash' => $hash,
            'phpFile' => $phpFileName,
            'timestamp' => time(),
        ];

        $this->saveCacheIndex($cacheIndex);

        return $cachedPhpPath;
    }

    /**
     * Invalidates cache entry for a specific file.
     */
    public function invalidateCache(string $phelFilePath): void
    {
        $cacheIndex = $this->loadCacheIndex();
        $normalizedPath = $this->normalizePath($phelFilePath);

        if (isset($cacheIndex[$normalizedPath])) {
            $phpFile = $cacheIndex[$normalizedPath]['phpFile'];
            $cachedPhpPath = $this->getCacheDirectory() . DIRECTORY_SEPARATOR . $phpFile;

            if (file_exists($cachedPhpPath)) {
                unlink($cachedPhpPath);
            }

            unset($cacheIndex[$normalizedPath]);
            $this->saveCacheIndex($cacheIndex);
        }
    }

    /**
     * Clears all cache entries.
     */
    public function clearAll(): void
    {
        $cacheDir = $this->getCacheDirectory();

        /** @var list<string> $files */
        $files = glob($cacheDir . DIRECTORY_SEPARATOR . '*.php');
        foreach ($files as $file) {
            unlink($file);
        }

        $this->saveCacheIndex([]);
    }

    /**
     * @return array<string, array{hash: string, phpFile: string, timestamp: int}>
     */
    private function loadCacheIndex(): array
    {
        $indexPath = $this->getCacheDirectory() . DIRECTORY_SEPARATOR . self::CACHE_INDEX_FILE;

        if (!file_exists($indexPath)) {
            return [];
        }

        $content = file_get_contents($indexPath);
        $decoded = json_decode($content ?: '{}', true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array{hash: string, phpFile: string, timestamp: int}> $cacheIndex
     */
    private function saveCacheIndex(array $cacheIndex): void
    {
        $indexPath = $this->getCacheDirectory() . DIRECTORY_SEPARATOR . self::CACHE_INDEX_FILE;
        file_put_contents($indexPath, json_encode($cacheIndex, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    /**
     * Normalizes file path for consistent cache key.
     */
    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', realpath($path) ?: $path);
    }
}
