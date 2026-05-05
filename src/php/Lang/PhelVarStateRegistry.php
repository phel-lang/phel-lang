<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;

use function array_key_exists;

/**
 * Singleton side table for {@see PhelVar} per-var state that lives outside
 * the value/meta slots managed by {@see Registry}.
 *
 * Vars are global handles to a single registry slot; equality and hashing
 * are based on `(ns, name)`. Because every `PhelVar` instance for the same
 * slot is interchangeable, mutable per-var state (an override metadata map,
 * the watch fn table, and a cached dynamic-flag lookup) cannot live on the
 * handle without surprising semantics. Storing it here, keyed by
 * `ns/name`, keeps `PhelVar` immutable while still letting `alter-meta!`,
 * `reset-meta!`, `add-watch`, and `remove-watch` mutate the canonical
 * state for a definition.
 */
final class PhelVarStateRegistry
{
    private static ?self $instance = null;

    /** @var array<string, ?PersistentMapInterface> */
    private array $metaOverrides = [];

    /** @var array<string, array<string, callable>> */
    private array $watches = [];

    /** @var array<string, bool> */
    private array $dynamicCache = [];

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function clear(): void
    {
        $this->metaOverrides = [];
        $this->watches = [];
        $this->dynamicCache = [];
    }

    public function hasMetaOverride(string $ns, string $name): bool
    {
        return array_key_exists($this->key($ns, $name), $this->metaOverrides);
    }

    public function getMetaOverride(string $ns, string $name): ?PersistentMapInterface
    {
        return $this->metaOverrides[$this->key($ns, $name)] ?? null;
    }

    public function setMetaOverride(string $ns, string $name, ?PersistentMapInterface $meta): void
    {
        $this->metaOverrides[$this->key($ns, $name)] = $meta;
    }

    public function clearMetaOverride(string $ns, string $name): void
    {
        unset($this->metaOverrides[$this->key($ns, $name)]);
    }

    public function addWatch(string $ns, string $name, string $watchKey, callable $fn): void
    {
        $this->watches[$this->key($ns, $name)][$watchKey] = $fn;
    }

    public function removeWatch(string $ns, string $name, string $watchKey): void
    {
        $key = $this->key($ns, $name);
        unset($this->watches[$key][$watchKey]);
        if (isset($this->watches[$key]) && $this->watches[$key] === []) {
            unset($this->watches[$key]);
        }
    }

    /**
     * @return array<string, callable>
     */
    public function getWatches(string $ns, string $name): array
    {
        return $this->watches[$this->key($ns, $name)] ?? [];
    }

    public function rememberDynamic(string $ns, string $name, bool $isDynamic): void
    {
        $this->dynamicCache[$this->key($ns, $name)] = $isDynamic;
    }

    public function getCachedDynamic(string $ns, string $name): ?bool
    {
        return $this->dynamicCache[$this->key($ns, $name)] ?? null;
    }

    public function invalidateDynamicCache(string $ns, string $name): void
    {
        unset($this->dynamicCache[$this->key($ns, $name)]);
    }

    private function key(string $ns, string $name): string
    {
        return $ns . '/' . $name;
    }
}
