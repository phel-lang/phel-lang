<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use ParseError;

use function count;
use function function_exists;
use function is_array;
use function is_string;
use function md5;
use function sprintf;
use function token_get_all;

use const TOKEN_PARSE;

/**
 * Caches compiled PHP code keyed by source file path with content-hash
 * validation. Multiple files that share a namespace via `(in-ns ...)`
 * each get their own cache entry, so they do not clobber one another.
 *
 * Environment data (refers/aliases) is keyed by namespace because it
 * is shared across all files of the namespace.
 */
final class CompiledCodeCache
{
    private const string VERSION = '1.2';

    /** @var array<string, array{namespace: string, source_hash: string, compiled_path: string, last_accessed: int}> */
    private array $entries = [];

    private bool $loaded = false;

    public function __construct(
        private readonly string $cacheDir,
        private readonly string $phelVersion = '',
        private readonly int $maxEntries = 500,
    ) {}

    /**
     * Returns the path to the cached compiled PHP file if it exists and
     * matches the source content, or null otherwise.
     *
     * @param string $sourcePath Absolute path to the .phel source file
     * @param string $sourceHash MD5 hash of the source file content
     */
    public function get(string $sourcePath, string $sourceHash): ?string
    {
        $this->loadEntries();

        $entry = $this->entries[$sourcePath] ?? null;
        if ($entry === null) {
            return null;
        }

        if ($entry['source_hash'] !== $sourceHash) {
            return null;
        }

        $compiledPath = $this->getCompiledPath($sourcePath, $entry['namespace']);

        if (!file_exists($compiledPath)) {
            return null;
        }

        $this->entries[$sourcePath]['last_accessed'] = time();

        return $compiledPath;
    }

    /**
     * Caches compiled PHP code for a source file.
     */
    public function put(string $sourcePath, string $namespace, string $sourceHash, string $phpCode): void
    {
        $this->loadEntries();
        $this->ensureCacheDir();

        $compiledPath = $this->getCompiledPath($sourcePath, $namespace);
        $fullPhpCode = "<?php\n" . $phpCode;

        if (!$this->isValidPhp($fullPhpCode)) {
            return;
        }

        if (!$this->atomicWrite($compiledPath, $fullPhpCode)) {
            return;
        }

        $this->entries[$sourcePath] = [
            'namespace' => $namespace,
            'source_hash' => $sourceHash,
            'compiled_path' => $compiledPath,
            'last_accessed' => time(),
        ];

        $this->evictLRU();
        $this->saveEntries();

        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($compiledPath);
        }
    }

    /**
     * Returns the path where compiled PHP for a source file would live.
     */
    public function getCompiledPath(string $sourcePath, string $namespace): string
    {
        return $this->getCachePath($namespace, $sourcePath, '.php');
    }

    /**
     * Returns the path where namespace-level environment data would live.
     * Environment data (refers/aliases) is shared across all files of the
     * namespace, so it is keyed by namespace alone.
     */
    public function getEnvironmentPath(string $namespace): string
    {
        $mungedNamespace = str_replace(['\\', '/'], '_', $namespace);

        return $this->cacheDir . '/compiled/' . $mungedNamespace . '.env.php';
    }

    public function putEnvironment(string $namespace, array $envData): void
    {
        $this->ensureCacheDir();

        $envPath = $this->getEnvironmentPath($namespace);
        $content = '<?php return ' . var_export($envData, true) . ';';

        $this->atomicWrite($envPath, $content);
    }

    public function getEnvironment(string $namespace): ?array
    {
        $envPath = $this->getEnvironmentPath($namespace);

        if (!file_exists($envPath)) {
            return null;
        }

        return require $envPath;
    }

    /**
     * Invalidates the cached compiled code for a single source file.
     * Environment data for the namespace is NOT removed because other
     * files of the same namespace may still rely on it.
     */
    public function invalidate(string $sourcePath): void
    {
        $this->loadEntries();

        $entry = $this->entries[$sourcePath] ?? null;
        if ($entry === null) {
            return;
        }

        $compiledPath = $this->getCompiledPath($sourcePath, $entry['namespace']);
        if (file_exists($compiledPath)) {
            @unlink($compiledPath);
        }

        unset($this->entries[$sourcePath]);
        $this->saveEntries();
    }

    /**
     * Clears every cached compiled file and every namespace env file.
     */
    public function clear(): void
    {
        $compiledDir = $this->cacheDir . '/compiled';
        if (is_dir($compiledDir)) {
            $files = glob($compiledDir . '/*.php');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }

        $this->entries = [];
        $this->saveEntries();
    }

    private function getCacheVersion(): string
    {
        return self::VERSION . ':' . $this->phelVersion;
    }

    private function loadEntries(): void
    {
        if ($this->loaded) {
            return;
        }

        $indexFile = $this->getIndexFile();
        if (!file_exists($indexFile)) {
            $this->entries = [];
            $this->loaded = true;
            return;
        }

        $data = @include $indexFile;
        if (!is_array($data) || !isset($data['version']) || $data['version'] !== $this->getCacheVersion()) {
            $this->entries = [];
            $this->loaded = true;
            return;
        }

        $entries = [];
        foreach ($data['entries'] ?? [] as $sourcePath => $entryData) {
            if (is_array($entryData)
                && isset($entryData['namespace'], $entryData['source_hash'], $entryData['compiled_path'])
                && is_string($entryData['namespace'])
                && is_string($entryData['source_hash'])
                && is_string($entryData['compiled_path'])
            ) {
                $entries[$sourcePath] = [
                    'namespace' => $entryData['namespace'],
                    'source_hash' => $entryData['source_hash'],
                    'compiled_path' => $entryData['compiled_path'],
                    'last_accessed' => $entryData['last_accessed'] ?? time(),
                ];
            }
        }

        $this->entries = $entries;
        $this->loaded = true;
    }

    private function saveEntries(): void
    {
        $this->ensureCacheDir();

        $dir = $this->cacheDir;
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        $indexFile = $this->getIndexFile();
        $handle = @fopen($indexFile, 'c+');
        if ($handle === false) {
            return;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return;
        }

        try {
            // Merge on-disk entries with in-memory entries. A (load ...) form
            // creates a nested cache instance that writes its own sub-file
            // entries to disk; without this merge, the outer instance would
            // truncate those entries when it saves its own, forcing sub-files
            // to recompile on the next run.
            $merged = $this->mergeWithDiskEntries($handle);

            ftruncate($handle, 0);
            rewind($handle);
            $content = '<?php return ' . var_export([
                'version' => $this->getCacheVersion(),
                'entries' => $merged,
            ], true) . ';';
            fwrite($handle, $content);
            $this->entries = $merged;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($indexFile, true);
        }
    }

    /**
     * Reads the current on-disk index and merges it with in-memory entries.
     * In-memory entries win on conflict so a fresh `put` overrides older data.
     *
     * @param resource $handle Open, exclusively-locked file handle for the index file
     *
     * @return array<string, array{namespace: string, source_hash: string, compiled_path: string, last_accessed: int}>
     */
    private function mergeWithDiskEntries($handle): array
    {
        rewind($handle);
        $currentContent = stream_get_contents($handle);
        if ($currentContent === false || $currentContent === '') {
            return $this->entries;
        }

        $diskEntries = $this->parseIndexContent($currentContent);

        return array_merge($diskEntries, $this->entries);
    }

    /**
     * @return array<string, array{namespace: string, source_hash: string, compiled_path: string, last_accessed: int}>
     */
    private function parseIndexContent(string $content): array
    {
        $tempFile = @tempnam(sys_get_temp_dir(), 'phel_cache_merge_');
        if ($tempFile === false) {
            return [];
        }

        try {
            if (file_put_contents($tempFile, $content) === false) {
                return [];
            }

            /** @psalm-suppress UnresolvableInclude */
            $data = @include $tempFile;
        } finally {
            @unlink($tempFile);
        }

        if (!is_array($data) || !isset($data['version']) || $data['version'] !== $this->getCacheVersion()) {
            return [];
        }

        $entries = [];
        foreach ($data['entries'] ?? [] as $sourcePath => $entryData) {
            if (is_array($entryData)
                && isset($entryData['namespace'], $entryData['source_hash'], $entryData['compiled_path'])
                && is_string($entryData['namespace'])
                && is_string($entryData['source_hash'])
                && is_string($entryData['compiled_path'])
            ) {
                $entries[$sourcePath] = [
                    'namespace' => $entryData['namespace'],
                    'source_hash' => $entryData['source_hash'],
                    'compiled_path' => $entryData['compiled_path'],
                    'last_accessed' => $entryData['last_accessed'] ?? time(),
                ];
            }
        }

        return $entries;
    }

    private function getIndexFile(): string
    {
        return $this->cacheDir . '/compiled-index.php';
    }

    /**
     * Compiled-file path includes both the munged namespace (so files
     * remain debuggable) and a short hash of the source path (so files
     * sharing a namespace do not collide).
     */
    private function getCachePath(string $namespace, string $sourcePath, string $suffix): string
    {
        $mungedNamespace = str_replace(['\\', '/'], '_', $namespace);
        $sourceFingerprint = substr(md5($sourcePath), 0, 8);

        return $this->cacheDir . '/compiled/' . $mungedNamespace . '__' . $sourceFingerprint . $suffix;
    }

    /**
     * @return bool True on success, false on failure
     */
    private function atomicWrite(string $path, string $content): bool
    {
        $tempPath = $path . '.tmp.' . uniqid('', true);
        if (file_put_contents($tempPath, $content) === false) {
            trigger_error(
                sprintf('Phel cache: failed to write temp file "%s"', $tempPath),
                E_USER_WARNING,
            );
            return false;
        }

        if (!rename($tempPath, $path)) {
            trigger_error(
                sprintf('Phel cache: failed to rename "%s" to "%s"', $tempPath, $path),
                E_USER_WARNING,
            );
            @unlink($tempPath);
            return false;
        }

        return true;
    }

    private function ensureCacheDir(): void
    {
        $compiledDir = $this->cacheDir . '/compiled';
        if (!is_dir($compiledDir)) {
            $oldUmask = umask(0);
            try {
                @mkdir($compiledDir, 0755, true);
            } finally {
                umask($oldUmask);
            }
        }
    }

    private function isValidPhp(string $phpCode): bool
    {
        try {
            // @phpstan-ignore function.resultUnused (we only care if it throws)
            token_get_all($phpCode, TOKEN_PARSE);
            return true;
        } catch (ParseError) {
            return false;
        }
    }

    /**
     * Evicts least-recently-used entries when the entry count exceeds
     * `$maxEntries`. Removes ~10% of the oldest entries.
     */
    private function evictLRU(): void
    {
        if (count($this->entries) <= $this->maxEntries) {
            return;
        }

        uasort($this->entries, static fn($a, $b): int => $a['last_accessed'] <=> $b['last_accessed']);

        $evictCount = max(1, (int) floor($this->maxEntries / 10));

        $evicted = 0;
        foreach (array_keys($this->entries) as $sourcePath) {
            if ($evicted >= $evictCount) {
                break;
            }

            $entry = $this->entries[$sourcePath];
            $compiledPath = $this->getCompiledPath($sourcePath, $entry['namespace']);
            if (file_exists($compiledPath)) {
                @unlink($compiledPath);
            }

            unset($this->entries[$sourcePath]);
            ++$evicted;
        }
    }
}
