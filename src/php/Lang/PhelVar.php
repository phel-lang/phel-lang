<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use RuntimeException;

use function get_debug_type;
use function is_callable;
use function sprintf;

/**
 * A first-class reference to a global definition created by `def`.
 *
 * Each `PhelVar` is a stable handle to a single registry slot identified by
 * namespace and name. The handle stays valid as the slot's value changes;
 * `deref()` always reads the current value, `alterRoot()` replaces it,
 * `meta()` returns the metadata attached at definition time (or the
 * override installed by `alter-meta!` / `reset-meta!`).
 *
 * `PhelVar` is also callable: invoking the handle (`(#'+ 1 2)`) forwards
 * the arguments to its current root value when that value is itself
 * callable.
 *
 * Equality is structural over `(ns, name)` so two handles to the same slot
 * compare equal; identity-style equality is provided by the same operator
 * since `PhelVar` has no further state. Per-var mutable state (meta
 * override, watches, dynamic-flag cache) lives in
 * {@see PhelVarStateRegistry}, keyed by `ns/name`, so all handles to the
 * same slot observe the same state.
 */
final readonly class PhelVar implements EqualsInterface, FnInterface, HashableInterface, MetaInterface
{
    public function __construct(
        private string $namespace,
        private string $name,
    ) {}

    /**
     * Invokes the var's current root value with the supplied arguments.
     * Throws when the root value is not callable so the failure surfaces
     * at the call site instead of being swallowed by PHP's "uncallable"
     * error path.
     */
    public function __invoke(mixed ...$args): mixed
    {
        $value = $this->deref();
        if (!is_callable($value)) {
            throw new RuntimeException(sprintf(
                "Cannot invoke #'%s: root value is not callable (got %s)",
                $this->getFullName(),
                get_debug_type($value),
            ));
        }

        return $value(...$args);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Reads the metadata attached to this var's underlying definition.
     * `MetaInterface` lets `(meta #'sym)` route through the standard runtime
     * path instead of the quoted-symbol special case.
     */
    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta();
    }

    /**
     * Vars are global handles to a single registry slot, so attaching
     * different metadata per handle is meaningless: the call returns the
     * receiver unchanged. Use `alter-meta!` / `reset-meta!` to mutate the
     * canonical per-var metadata, or redefine the var to rotate the
     * metadata attached to the underlying definition.
     */
    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function withMeta(?PersistentMapInterface $meta): static
    {
        return $this;
    }

    public function getFullName(): string
    {
        return $this->namespace . '/' . $this->name;
    }

    /**
     * Returns the current root value of this var. Throws if the underlying
     * definition has been removed from the registry.
     */
    public function deref(): mixed
    {
        $registry = Registry::getInstance();
        if (!$registry->isDefined($this->namespace, $this->name)) {
            throw new RuntimeException(sprintf(
                "Cannot deref var #'%s: definition has been removed from the registry",
                $this->getFullName(),
            ));
        }

        return $registry->getDefinition($this->namespace, $this->name);
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function meta(): ?PersistentMapInterface
    {
        $state = PhelVarStateRegistry::getInstance();
        if ($state->hasMetaOverride($this->namespace, $this->name)) {
            return $state->getMetaOverride($this->namespace, $this->name);
        }

        return Registry::getInstance()->getDefinitionMetaData($this->namespace, $this->name);
    }

    /**
     * Replaces this var's per-var metadata with `$f($currentMeta, ...$args)`
     * and returns the new metadata map. The previous override (or the
     * definition meta when no override was set) is fed in as the first
     * argument.
     */
    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function alterMeta(callable $f, mixed ...$args): ?PersistentMapInterface
    {
        $next = $f($this->meta(), ...$args);
        PhelVarStateRegistry::getInstance()->setMetaOverride($this->namespace, $this->name, $next);

        return $next;
    }

    /**
     * Installs `$meta` as this var's per-var metadata, replacing any prior
     * override. Returns the installed map.
     */
    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     *
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function resetMeta(?PersistentMapInterface $meta): ?PersistentMapInterface
    {
        PhelVarStateRegistry::getInstance()->setMetaOverride($this->namespace, $this->name, $meta);

        return $meta;
    }

    /**
     * Sets the registry slot to `$f($current, ...$args)` and returns the
     * new value. Metadata attached to the definition is preserved. Watch
     * functions registered via {@see self::addWatch()} are notified after
     * the slot has been updated.
     */
    public function alterRoot(callable $f, mixed ...$args): mixed
    {
        $registry = Registry::getInstance();
        if (!$registry->isDefined($this->namespace, $this->name)) {
            throw new RuntimeException(sprintf(
                "Cannot alter-var-root on #'%s: definition has been removed from the registry",
                $this->getFullName(),
            ));
        }

        $current = $registry->getDefinition($this->namespace, $this->name);
        $next = $f($current, ...$args);
        $meta = $registry->getDefinitionMetaData($this->namespace, $this->name);
        $registry->addDefinition($this->namespace, $this->name, $next, $meta);

        $this->notifyWatches($current, $next);

        return $next;
    }

    /**
     * Registers a watch function under `$key`. The function is called with
     * `($keyword, $var, $oldValue, $newValue)` after a successful
     * `alterRoot()`. Re-adding the same key replaces the previous fn.
     */
    public function addWatch(string $key, callable $fn): void
    {
        PhelVarStateRegistry::getInstance()->addWatch($this->namespace, $this->name, $key, $fn);
    }

    /**
     * Removes the watch function registered under `$key`, if any.
     */
    public function removeWatch(string $key): void
    {
        PhelVarStateRegistry::getInstance()->removeWatch($this->namespace, $this->name, $key);
    }

    /**
     * True when this var carries `^:dynamic` metadata. The lookup is cached
     * by {@see PhelVarStateRegistry} and invalidated automatically when
     * `alter-meta!` / `reset-meta!` mutate the per-var metadata.
     */
    public function isDynamic(): bool
    {
        return PhelVarStateRegistry::getInstance()->isDynamic($this->namespace, $this->name);
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self
            && $this->namespace === $other->namespace
            && $this->name === $other->name;
    }

    public function hash(): int
    {
        return crc32($this->getFullName());
    }

    private function notifyWatches(mixed $oldValue, mixed $newValue): void
    {
        $watches = PhelVarStateRegistry::getInstance()->getWatches($this->namespace, $this->name);
        if ($watches === []) {
            return;
        }

        foreach ($watches as $key => $callback) {
            $callback(Keyword::create($key), $this, $oldValue, $newValue);
        }
    }
}
