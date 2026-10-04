<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use ParseError;
use Phel\Shared\Facade\CompilerFacadeInterface;

use function function_exists;
use function is_array;
use function is_string;

/**
 * Reads and writes the compiled-code cache index (`compiled-index.php`), a
 * version-stamped map of source path to cache entry.
 *
 * Writes go through {@see LockedPhpCacheWriter}, which merges the current
 * on-disk entries under a lock and replaces the file by a rename, so a nested
 * cache instance created by a `(load ...)` form cannot clobber the entries
 * written by the outer instance, and a reader never sees half a file. Entry shapes are validated on every read so a partially
 * written or version-mismatched index degrades to "empty" rather than
 * surfacing malformed data.
 *
 * @phpstan-import-type DeprecationRecord from CompilerFacadeInterface
 *
 * @phpstan-type CacheEntry array{namespace: string, source_hash: string, compiled_path: string, last_accessed: int, deprecations: list<DeprecationRecord>}
 *
 * @internal
 */
final readonly class CacheIndexFile
{
    // Bump when the entry structure changes (namespace/source_hash/compiled_path/last_accessed keys)
    // OR when the emitted PHP format changes within a release: the per-source `source_hash` only
    // tracks the .phel source, so a compiler change that alters output for unchanged source (e.g. the
    // #2729 cross-fn `:tag` inference) leaves stale compiled files that a same-version cache would
    // keep serving. Bumping here rejects the whole index once, forcing a cold recompile.
    // Decoupled from the Phel version for cache stability across minor releases.
    private const string INDEX_FORMAT_VERSION = '1.8';

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

        try {
            $data = @include $indexFile;
        } catch (ParseError) {
            return [];
        }

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

        $indexFile = $this->directory->indexFile();
        $merged = $entries;
        $written = LockedPhpCacheWriter::write($indexFile, function () use ($indexFile, $entries, $tombstones, &$merged): array {
            // Merge on-disk entries with in-memory entries. A (load ...) form
            // creates a nested cache instance that writes its own sub-file
            // entries to disk; without this merge, the outer instance would
            // drop those entries when it saves its own, forcing sub-files
            // to recompile on the next run.
            $merged = $this->mergeWithDiskEntries($indexFile, $entries, $tombstones);

            return [
                'version' => $this->version(),
                'entries' => $merged,
            ];
        });

        if (!$written) {
            return $entries;
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
     * @param array<string, CacheEntry> $entries
     * @param array<string, true>       $tombstones
     *
     * @return array<string, CacheEntry>
     */
    private function mergeWithDiskEntries(string $indexFile, array $entries, array $tombstones): array
    {
        $currentContent = @file_get_contents($indexFile);
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
        } catch (ParseError) {
            return [];
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
                    'deprecations' => $this->deprecationRecords($entryData['deprecations'] ?? null),
                ];
            }
        }

        return $entries;
    }

    /**
     * @return list<DeprecationRecord>
     */
    private function deprecationRecords(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $records = [];
        foreach ($raw as $record) {
            if (is_array($record) && isset($record['message']) && is_string($record['message'])) {
                $records[] = ['message' => $record['message'], 'announced' => (bool) ($record['announced'] ?? false)];
            }
        }

        return $records;
    }
}
