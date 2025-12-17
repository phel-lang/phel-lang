<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use ParseError;

use function count;
use function function_exists;
use function is_array;
use function is_string;
use function sprintf;
use function token_get_all;

use const TOKEN_PARSE;

/**
 * Caches compiled PHP code indexed by namespace with content-hash validation.
 */
final class CompiledCodeCache
{
    private const string VERSION = '1.0';

    /** @var array<string, array{source_hash: string, compiled_path: string, last_accessed: int}> */
    private array $entries = [];

    private bool $loaded = false;

    public function __construct(
        private readonly string $cacheDir,
        private readonly string $phelVersion = '',
        private readonly int $maxEntries = 500,
    ) {
    }

    /**
     * Returns the path to the cached compiled PHP file if it exists and is valid.
     *
     * @param string $namespace  The Phel namespace
     * @param string $sourceHash MD5 hash of the source file content
     *
     * @return string|null Path to cached file, or null if not cached or invalid
     */
    public function get(string $namespace, string $sourceHash): ?string
    {
        $this->loadEntries();

        $entry = $this->entries[$namespace] ?? null;
        if ($entry === null) {
            return null;
        }

        // Validate source hash matches
        if ($entry['source_hash'] !== $sourceHash) {
            return null;
        }

        // Validate compiled file still exists
        if (!file_exists($entry['compiled_path'])) {
            return null;
        }

        // Update last_accessed timestamp for LRU tracking
        $this->entries[$namespace]['last_accessed'] = time();

        return $entry['compiled_path'];
    }

    /**
     * Caches compiled PHP code for a namespace.
     *
     * @param string $namespace  The Phel namespace
     * @param string $sourceHash MD5 hash of the source file content
     * @param string $phpCode    The compiled PHP code (without <?php header)
     */
    public function put(string $namespace, string $sourceHash, string $phpCode): void
    {
        $this->loadEntries();
        $this->ensureCacheDir();

        $compiledPath = $this->getCompiledPath($namespace);
        // Note: Do NOT use strict_types here. The RequireEvaluator (used during
        // first-run compilation) doesn't use strict types, and some Phel code
        // relies on PHP's implicit type coercion (e.g., float to int for str_repeat).
        $fullPhpCode = "<?php\n" . $phpCode;

        // Validate PHP syntax before caching to avoid storing malformed code
        if (!$this->isValidPhp($fullPhpCode)) {
            return;
        }

        // Use atomic write: write to temp file then rename (atomic on POSIX)
        $tempPath = $compiledPath . '.tmp.' . uniqid('', true);
        if (file_put_contents($tempPath, $fullPhpCode) === false) {
            trigger_error(
                sprintf('Phel cache: failed to write temp file "%s"', $tempPath),
                E_USER_WARNING,
            );
            return;
        }

        if (!rename($tempPath, $compiledPath)) {
            trigger_error(
                sprintf('Phel cache: failed to rename "%s" to "%s"', $tempPath, $compiledPath),
                E_USER_WARNING,
            );
            @unlink($tempPath);
            return;
        }

        $this->entries[$namespace] = [
            'source_hash' => $sourceHash,
            'compiled_path' => $compiledPath,
            'last_accessed' => time(),
        ];

        // Evict LRU entries if we exceed max capacity
        $this->evictLRU();

        $this->saveEntries();

        // Compile with OPcache if available
        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($compiledPath);
        }
    }

    /**
     * Gets the path where compiled PHP for a namespace would be stored.
     */
    public function getCompiledPath(string $namespace): string
    {
        $mungedNamespace = str_replace(['\\', '/'], '_', $namespace);
        return $this->cacheDir . '/compiled/' . $mungedNamespace . '.php';
    }

    /**
     * Invalidates the cache for a specific namespace.
     */
    public function invalidate(string $namespace): void
    {
        $this->loadEntries();

        if (!isset($this->entries[$namespace])) {
            return;
        }

        $compiledPath = $this->entries[$namespace]['compiled_path'];
        if (file_exists($compiledPath)) {
            @unlink($compiledPath);
        }

        unset($this->entries[$namespace]);
        $this->saveEntries();
    }

    /**
     * Clears all cached compiled code.
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

    /**
     * Returns the cache version string combining internal format version and Phel version.
     * This ensures cache is invalidated when either changes.
     */
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
        foreach ($data['entries'] ?? [] as $namespace => $entryData) {
            if (is_array($entryData)
                && isset($entryData['source_hash'], $entryData['compiled_path'])
                && is_string($entryData['source_hash'])
                && is_string($entryData['compiled_path'])
            ) {
                $entries[$namespace] = [
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
        $handle = @fopen($indexFile, 'c');
        if ($handle === false) {
            return;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return;
        }

        try {
            ftruncate($handle, 0);
            rewind($handle);
            $content = '<?php return ' . var_export([
                'version' => $this->getCacheVersion(),
                'entries' => $this->entries,
            ], true) . ';';
            fwrite($handle, $content);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($indexFile, true);
        }
    }

    private function getIndexFile(): string
    {
        return $this->cacheDir . '/compiled-index.php';
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

    /**
     * Validates that the given PHP code has valid syntax.
     * Uses PHP's tokenizer which will throw ParseError for invalid syntax.
     */
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
     * Evicts least recently used entries when cache exceeds max capacity.
     * Removes approximately 10% of oldest entries to make room for new ones.
     */
    private function evictLRU(): void
    {
        if (count($this->entries) <= $this->maxEntries) {
            return;
        }

        // Sort entries by last_accessed (oldest first)
        uasort($this->entries, static fn ($a, $b): int => $a['last_accessed'] <=> $b['last_accessed']);

        // Calculate how many to evict (10% of max, minimum 1)
        $evictCount = max(1, (int) floor($this->maxEntries / 10));

        // Evict oldest entries
        $evicted = 0;
        foreach ($this->entries as $namespace => $entry) {
            if ($evicted >= $evictCount) {
                break;
            }

            if (file_exists($entry['compiled_path'])) {
                @unlink($entry['compiled_path']);
            }

            unset($this->entries[$namespace]);
            ++$evicted;
        }
    }
}
