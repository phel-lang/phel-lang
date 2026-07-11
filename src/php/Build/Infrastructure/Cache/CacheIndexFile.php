<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use function function_exists;
use function is_array;
use function is_string;

/**
 * Reads and writes the compiled-code cache index (`compiled-index.php`), a
 * version-stamped map of source path to cache entry.
 *
 * Writes go through an exclusive `flock` and merge the current on-disk
 * entries before truncating, so a nested cache instance created by a
 * `(load ...)` form cannot clobber the entries written by the outer
 * instance. Entry shapes are validated on every read so a partially
 * written or version-mismatched index degrades to "empty" rather than
 * surfacing malformed data.
 *
 * @phpstan-type CacheEntry array{namespace: string, source_hash: string, compiled_path: string, last_accessed: int}
 */
final readonly class CacheIndexFile
{
    // Bump when the entry structure changes (namespace/source_hash/compiled_path/last_accessed keys)
    // OR when the emitted PHP format changes within a release: the per-source `source_hash` only
    // tracks the .phel source, so a compiler change that alters output for unchanged source (e.g. the
    // #2729 cross-fn `:tag` inference) leaves stale compiled files that a same-version cache would
    // keep serving. Bumping here rejects the whole index once, forcing a cold recompile.
    // Decoupled from the Phel version for cache stability across minor releases.
    private const string INDEX_FORMAT_VERSION = '1.3';

    public function __construct(
        private CacheDirectory $directory,
        private string $phelVersion = '',
    ) {}

    public function version(): string
    {
        return self::INDEX_FORMAT_VERSION . ':' . $this->phelVersion;
    }

    /**
     * Loads and validates the on-disk entries, or returns an empty map when
     * the index is missing, unreadable, or stamped with a different version.
     *
     * @return array<string, CacheEntry>
     */
    public function load(): array
    {
        $indexFile = $this->directory->indexFile();
        if (!file_exists($indexFile)) {
            return [];
        }

        $data = @include $indexFile;

        return $this->normalize($data);
    }

    /**
     * Merges `$entries` over the current on-disk index (dropping
     * `$tombstones`) under an exclusive lock and rewrites the index.
     * Returns the merged map that is now on disk; on any I/O failure the
     * passed-in `$entries` are returned unchanged.
     *
     * @param array<string, CacheEntry> $entries
     * @param array<string, true>       $tombstones
     *
     * @return array<string, CacheEntry>
     */
    public function save(array $entries, array $tombstones): array
    {
        $this->directory->ensure();

        $dir = $this->directory->root();
        if (!is_dir($dir) || !is_writable($dir)) {
            return $entries;
        }

        $indexFile = $this->directory->indexFile();
        $handle = @fopen($indexFile, 'c+');
        if ($handle === false) {
            return $entries;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return $entries;
        }

        try {
            // Merge on-disk entries with in-memory entries. A (load ...) form
            // creates a nested cache instance that writes its own sub-file
            // entries to disk; without this merge, the outer instance would
            // truncate those entries when it saves its own, forcing sub-files
            // to recompile on the next run.
            $merged = $this->mergeWithDiskEntries($handle, $entries, $tombstones);

            ftruncate($handle, 0);
            rewind($handle);
            $content = '<?php return ' . var_export([
                'version' => $this->version(),
                'entries' => $merged,
            ], true) . ';';
            fwrite($handle, $content);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($indexFile, true);
        }

        return $merged;
    }

    /**
     * Reads the current on-disk index and merges it with in-memory entries.
     * In-memory entries win on conflict so a fresh `put` overrides older data.
     *
     * @param resource                  $handle     Open, exclusively-locked file handle for the index file
     * @param array<string, CacheEntry> $entries
     * @param array<string, true>       $tombstones
     *
     * @return array<string, CacheEntry>
     */
    private function mergeWithDiskEntries($handle, array $entries, array $tombstones): array
    {
        rewind($handle);
        $currentContent = stream_get_contents($handle);
        if ($currentContent === false || $currentContent === '') {
            return $entries;
        }

        $diskEntries = $this->parseIndexContent($currentContent);
        foreach (array_keys($tombstones) as $tombstoned) {
            unset($diskEntries[$tombstoned]);
        }

        return array_merge($diskEntries, $entries);
    }

    /**
     * @return array<string, CacheEntry>
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

        return $this->normalize($data);
    }

    /**
     * Validates the raw decoded index payload and copies through only
     * well-formed entries, defaulting `last_accessed` when absent.
     *
     * @return array<string, CacheEntry>
     */
    private function normalize(mixed $data): array
    {
        if (!is_array($data) || !isset($data['version']) || $data['version'] !== $this->version()) {
            return [];
        }

        $rawEntries = $data['entries'] ?? [];
        if (!is_array($rawEntries)) {
            return [];
        }

        $entries = [];
        foreach ($rawEntries as $sourcePath => $entryData) {
            if (is_string($sourcePath)
                && is_array($entryData)
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
}
