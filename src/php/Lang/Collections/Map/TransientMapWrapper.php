<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Phel\Lang\Collections\TransientStateTrait;
use Stringable;

/**
 * @template TKey
 * @template TValue
 *
 * @implements TransientMapInterface<TKey, TValue>
 */
final class TransientMapWrapper implements TransientMapInterface, Stringable
{
    use TransientStateTrait;

    /**
     * @param TransientMapInterface<TKey, TValue> $internal
     */
    public function __construct(private TransientMapInterface $internal) {}

    public function __toString(): string
    {
        return '<TransientMap count=' . $this->count() . '>';
    }

    /**
     * Lookup by key so transient maps stay callable like their persistent
     * counterparts: `((transient {:a 1}) :a) ; => 1`.
     *
     * @param TKey        $key
     * @param TValue|null $default
     *
     * @return TValue|null
     */
    public function __invoke(mixed $key, mixed $default = null): mixed
    {
        return $this->offsetGet($key) ?? $default;
    }

    public function contains($key): bool
    {
        return $this->internal->contains($key);
    }

    /**
     * @param mixed $key
     * @param mixed $value
     *
     * @return self<TKey, TValue>
     */
    public function put($key, $value): self
    {
        $this->ensureTransientActive();
        $this->internal = $this->internal->put($key, $value);

        return $this;
    }

    /**
     * @param mixed $key
     *
     * @return self<TKey, TValue>
     */
    public function remove($key): self
    {
        $this->ensureTransientActive();
        $this->internal = $this->internal->remove($key);

        return $this;
    }

    public function find($key)
    {
        return $this->internal->find($key);
    }

    public function count(): int
    {
        return $this->internal->count();
    }

    /**
     * @return PersistentMapInterface<TKey, TValue>
     */
    public function persistent(): PersistentMapInterface
    {
        $this->invalidateTransient();

        return $this->internal->persistent();
    }

    /**
     * @param TKey $offset
     *
     * @return TValue|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->internal->offsetGet($offset);
    }

    /**
     * @param TKey $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->internal->offsetExists($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->internal->offsetSet($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->internal->offsetUnset($offset);
    }
}
