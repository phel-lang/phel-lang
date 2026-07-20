<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;
use Phel\Build\Domain\Extractor\ExcludedScanPaths;

use function is_array;
use function is_int;
use function is_string;

final class PhpNamespaceCache implements NamespaceCacheInterface
{
    private const string VERSION = '1.0';

    private bool $dirty = false;

    private bool $shutdownRegistered = false;

    /** @var array<string, NamespaceCacheEntry> */
    private array $entries;

    public function __construct(
        private readonly string $cacheFile,
    ) {
        $this->entries = $this->loadEntriesFromFile();

        // `loadEntriesFromFile` may evict entries under always-excluded
        // segments; persist that cleanup at shutdown even if no put/remove
        // happens during the run.
        if ($this->dirty) {
            $this->registerShutdown();
        }
    }

    public function get(string $file): ?NamespaceCacheEntry
    {
        return $this->entries[$file] ?? null;
    }

    public function put(string $file, NamespaceCacheEntry $entry): void
    {
        $this->entries[$file] = $entry;
        $this->dirty = true;
        $this->registerShutdown();
    }

    public function remove(string $file): void
    {
        if (isset($this->entries[$file])) {
            unset($this->entries[$file]);
            $this->dirty = true;
            $this->registerShutdown();
        }
    }

    /**
     * @return list<string>
     */
    public function getAllFiles(): array
    {
        return array_keys($this->entries);
    }

    public function save(): void
    {
        if (!$this->dirty) {
            return;
        }

        $written = LockedPhpCacheWriter::write($this->cacheFile, function (): array {
            // Merge disk entries with our in-memory changes (ours take precedence)
            $diskEntries = $this->loadEntriesFromFile();
            $this->entries = array_merge($diskEntries, $this->entries);

            return $this->toArray();
        });

        if ($written) {
            $this->dirty = false;
        }
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->dirty = false;

        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    /**
     * @return array<string, NamespaceCacheEntry>
     */
    private function loadEntriesFromFile(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        $data = @include $this->cacheFile;
        if (!is_array($data) || !isset($data['version']) || $data['version'] !== self::VERSION) {
            return [];
        }

        $rawEntries = $data['entries'] ?? [];
        if (!is_array($rawEntries)) {
            return [];
        }

        $entries = [];
        foreach ($rawEntries as $file => $entryData) {
            if (!is_string($file)) {
                continue;
            }

            // Drop entries whose path now lives under an always-excluded
            // segment (vendor/, worktrees/, .agents/, ...). Without this,
            // pre-existing cache entries resurface as primary definitions
            // and trigger duplicate-namespace warnings against real sources
            // every time the exclusion policy gains a new prefix.
            if (ExcludedScanPaths::isAlwaysExcluded($file)) {
                $this->dirty = true;
                continue;
            }

            if (is_array($entryData)
                && isset($entryData['mtime'], $entryData['namespace'], $entryData['dependencies'])
                && is_int($entryData['mtime'])
                && is_string($entryData['namespace'])
                && is_array($entryData['dependencies'])
            ) {
                /** @var array{mtime: int, namespace: string, dependencies: list<string>, isPrimaryDefinition?: bool} $entryData */
                $entries[$file] = NamespaceCacheEntry::fromArray($file, $entryData);
            }
        }

        return $entries;
    }

    /**
     * @return array{version: string, entries: array<string, array{mtime: int, namespace: string, dependencies: list<string>, isPrimaryDefinition: bool}>}
     */
    private function toArray(): array
    {
        $entries = [];
        foreach ($this->entries as $file => $entry) {
            $entries[$file] = $entry->toArray();
        }

        return [
            'version' => self::VERSION,
            'entries' => $entries,
        ];
    }

    private function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        register_shutdown_function([$this, 'save']);
        $this->shutdownRegistered = true;
    }
}
