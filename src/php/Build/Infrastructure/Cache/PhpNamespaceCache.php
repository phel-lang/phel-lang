<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;

use function dirname;
use function function_exists;
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

        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            $oldUmask = umask(0);
            @mkdir($dir, 0755, true);
            umask($oldUmask);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        $handle = @fopen($this->cacheFile, 'c');
        if ($handle === false) {
            return;
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return;
        }

        try {
            // Merge disk entries with our in-memory changes (ours take precedence)
            $diskEntries = $this->loadEntriesFromFile();
            $this->entries = array_merge($diskEntries, $this->entries);

            ftruncate($handle, 0);
            rewind($handle);
            $content = '<?php return ' . var_export($this->toArray(), true) . ';';
            fwrite($handle, $content);
            $this->dirty = false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->cacheFile, true);
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

        $entries = [];
        foreach ($data['entries'] ?? [] as $file => $entryData) {
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
