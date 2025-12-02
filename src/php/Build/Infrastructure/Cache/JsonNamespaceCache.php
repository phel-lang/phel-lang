<?php

declare(strict_types=1);

namespace Phel\Build\Infrastructure\Cache;

use Phel\Build\Domain\Cache\NamespaceCacheEntry;
use Phel\Build\Domain\Cache\NamespaceCacheInterface;

use function dirname;
use function is_array;
use function is_int;
use function is_string;

final class JsonNamespaceCache implements NamespaceCacheInterface
{
    private const string VERSION = '1.0';

    private bool $dirty = false;

    private bool $shutdownRegistered = false;

    /**
     * @param array<string, NamespaceCacheEntry> $entries
     */
    public function __construct(
        private readonly string $cacheFile,
        private array $entries = [],
    ) {
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

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return;
        }

        try {
            ftruncate($handle, 0);
            rewind($handle);
            $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                fwrite($handle, $json);
            }

            $this->dirty = false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
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

    public static function load(string $cacheFile): self
    {
        if (!file_exists($cacheFile)) {
            return new self($cacheFile);
        }

        $content = @file_get_contents($cacheFile);
        if ($content === false) {
            return new self($cacheFile);
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['version']) || $data['version'] !== self::VERSION) {
            return new self($cacheFile);
        }

        $entries = [];
        foreach ($data['entries'] ?? [] as $file => $entryData) {
            if (is_array($entryData)
                && isset($entryData['mtime'], $entryData['namespace'], $entryData['dependencies'])
                && is_int($entryData['mtime'])
                && is_string($entryData['namespace'])
                && is_array($entryData['dependencies'])
            ) {
                /** @var array{mtime: int, namespace: string, dependencies: list<string>} $entryData */
                $entries[$file] = NamespaceCacheEntry::fromArray($file, $entryData);
            }
        }

        return new self($cacheFile, $entries);
    }

    /**
     * @return array{version: string, entries: array<string, array{mtime: int, namespace: string, dependencies: list<string>}>}
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
