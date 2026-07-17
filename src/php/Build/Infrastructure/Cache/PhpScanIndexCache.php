<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Gacela\Framework\Cache\FileCache;
use Phel\Build\Domain\Cache\ScanIndexCacheInterface;
use Phel\Build\Domain\Cache\ScanIndexEntry;

use function is_array;
use function is_string;

/**
 * Persists directory-scan results to `<cacheDir>/scan-index.php` so that warm
 * runs can skip the `RecursiveDirectoryIterator` walk + per-file `filemtime`
 * sweep entirely when the source tree has not changed.
 *
 * Mirrors {@see PhpNamespaceCache}: a `var_export`ed PHP file, written through
 * an exclusive `flock` with a disk-merge so concurrent processes don't clobber
 * each other, flushed via `register_shutdown_function`.
 *
 * @phpstan-import-type SerializedScanIndexEntry from ScanIndexEntry
 */
final class PhpScanIndexCache implements ScanIndexCacheInterface
{
    use DeferredFlushTrait;

    private const string VERSION = '1.0';

    /** @var array<string, ScanIndexEntry> */
    private array $entries;

    public function __construct(
        private readonly string $cacheFile,
    ) {
        $this->entries = $this->loadEntriesFromFile();
    }

    public function get(string $dirSetKey): ?ScanIndexEntry
    {
        return $this->entries[$dirSetKey] ?? null;
    }

    public function put(string $dirSetKey, array $perDir, array $infos): void
    {
        $files = [];
        foreach ($infos as $info) {
            $mtime = @filemtime($info->getFile());
            if ($mtime === false) {
                // A file we cannot stat must not be persisted as a validation
                // anchor: skip the whole entry so we re-walk next time rather
                // than risk serving a stale set.
                return;
            }

            $files[] = ['file' => $info->getFile(), 'mtime' => $mtime];
        }

        $this->entries[$dirSetKey] = new ScanIndexEntry($perDir, $files, $infos);
        $this->markFlushPending();
    }

    public function save(): void
    {
        if (!$this->isFlushPending()) {
            return;
        }

        $written = LockedPhpCacheWriter::write($this->cacheFile, function (): array {
            // Merge disk entries with our in-memory changes (ours take precedence)
            $diskEntries = $this->loadEntriesFromFile();
            $this->entries = array_merge($diskEntries, $this->entries);

            return $this->toArray();
        });

        if ($written) {
            $this->clearFlushPending();
        }
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->clearFlushPending();

        FileCache::delete($this->cacheFile);
    }

    /**
     * @return array<string, ScanIndexEntry>
     */
    private function loadEntriesFromFile(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        $data = @include $this->cacheFile;
        if (!is_array($data) || ($data['version'] ?? null) !== self::VERSION) {
            return [];
        }

        $rawEntries = $data['entries'] ?? [];
        if (!is_array($rawEntries)) {
            return [];
        }

        $entries = [];
        foreach ($rawEntries as $dirSetKey => $entryData) {
            if (!is_string($dirSetKey)) {
                continue;
            }

            $entry = ScanIndexEntry::fromArray($entryData);
            if ($entry instanceof ScanIndexEntry) {
                $entries[$dirSetKey] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array{version: string, entries: array<string, SerializedScanIndexEntry>}
     */
    private function toArray(): array
    {
        $entries = [];
        foreach ($this->entries as $dirSetKey => $entry) {
            $entries[$dirSetKey] = $entry->toArray();
        }

        return [
            'version' => self::VERSION,
            'entries' => $entries,
        ];
    }
}
