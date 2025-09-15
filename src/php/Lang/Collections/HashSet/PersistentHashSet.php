<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\HasherInterface;
use Traversable;

/**
 * @template V
 *
 * @implements PersistentHashSetInterface<V>
 *
 * @extends AbstractType<PersistentHashSet<V>>
 */
final class PersistentHashSet extends AbstractType implements PersistentHashSetInterface
{
    private int $hashCache = 0;

    /**
     * @param PersistentMapInterface<V, V> $map
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly ?PersistentMapInterface $meta,
        private readonly PersistentMapInterface $map,
    ) {
    }

    /**
     * @param V $key
     *
     * @return ?V
     */
    public function __invoke(mixed $key)
    {
        return $this->map->find($key);
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->hasher, $meta, $this->map);
    }

    /**
     * @param V $key
     */
    public function contains($key): bool
    {
        return $this->map->contains($key);
    }

    /**
     * @param V $value
     */
    public function add($value): PersistentHashSetInterface
    {
        if ($this->contains($value)) {
            return $this;
        }

        return new self($this->hasher, $this->meta, $this->map->put($value, $value));
    }

    /**
     * @param V $value
     */
    public function remove($value): PersistentHashSetInterface
    {
        if ($this->contains($value)) {
            return new self($this->hasher, $this->meta, $this->map->remove($value));
        }

        return $this;
    }

    public function count(): int
    {
        return $this->map->count();
    }

    public function equals(mixed $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->count() !== $other->count()) {
            return false;
        }

        foreach ($this as $value) {
            if (!$other->contains($value)) {
                return false;
            }
        }

        return true;
    }

    public function hash(): int
    {
        if ($this->hashCache === 0) {
            foreach ($this->map as $value) {
                $this->hashCache += $this->hasher->hash($value);
            }
        }

        return $this->hashCache;
    }

    public function getIterator(): Traversable
    {
        foreach ($this->map as $value) {
            yield $value;
        }
    }

    public function asTransient(): TransientHashSet
    {
        return new TransientHashSet($this->hasher, $this->map->asTransient());
    }

    public function toPhpArray(): array
    {
        return iterator_to_array($this);
    }

    /**
     * Concatenates a value to the data structure.
     *
     * @param array<int, mixed> $xs The value to concatenate
     */
    public function concat($xs): PersistentHashSetInterface
    {
        $map = $this->asTransient();
        foreach ($xs as $x) {
            $map->add($x);
        }

        return $map->persistent();
    }
}
