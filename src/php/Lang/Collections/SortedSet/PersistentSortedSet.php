<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedSet;

use Closure;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\Collections\SortedMap\PersistentSortedMap;
use Phel\Lang\Collections\SortedMap\SortedArrayHelper;
use Phel\Lang\HasherInterface;
use Traversable;

/**
 * @template V
 *
 * @implements PersistentHashSetInterface<V>
 *
 * @extends AbstractType<PersistentSortedSet<V>>
 */
final class PersistentSortedSet extends AbstractType implements PersistentHashSetInterface
{
    private ?int $hashCache = null;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     * @param PersistentMapInterface<V, V>              $map
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly ?PersistentMapInterface $meta,
        private readonly PersistentMapInterface $map,
    ) {}

    /**
     * @param V $key
     *
     * @return ?V
     */
    public function __invoke(mixed $key)
    {
        return $this->map->find($key);
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
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
        $newMap = $this->map->put($value, $value);
        if ($newMap === $this->map) {
            return $this;
        }

        /** @var PersistentSortedMap<V, V> $newMap */
        return new self($this->hasher, $this->meta, $newMap);
    }

    /**
     * @param V $value
     */
    public function remove($value): PersistentHashSetInterface
    {
        $newMap = $this->map->remove($value);
        if ($newMap === $this->map) {
            return $this;
        }

        return new self($this->hasher, $this->meta, $newMap);
    }

    public function count(): int
    {
        return $this->map->count();
    }

    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }

        if (!$other instanceof PersistentHashSetInterface) {
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
        if ($this->hashCache === null) {
            $this->hashCache = $this->hasher->unorderedHash($this->map);
        }

        return $this->hashCache;
    }

    public function getIterator(): Traversable
    {
        foreach ($this->map as $value) {
            yield $value;
        }
    }

    /**
     * @return TransientSortedSet<V>
     */
    public function asTransient(): TransientSortedSet
    {
        /** @var TransientMapInterface<V, V> $transient */
        $transient = $this->map->asTransient();
        return new TransientSortedSet($this->hasher, $transient);
    }

    public function toPhpArray(): array
    {
        return iterator_to_array($this);
    }

    /**
     * @param array<int, mixed> $xs
     */
    public function concat($xs): PersistentHashSetInterface
    {
        $map = $this->asTransient();
        foreach ($xs as $x) {
            $map->add($x);
        }

        return $map->persistent();
    }

    /**
     * Returns the comparator of the underlying sorted map: the user comparator
     * adapted to always return an int, or the natural-order default.
     *
     * @return Closure(mixed, mixed): int
     */
    public function getEffectiveComparator(): Closure
    {
        if ($this->map instanceof PersistentSortedMap) {
            return $this->map->getEffectiveComparator();
        }

        return SortedArrayHelper::adaptForBinarySearch(SortedArrayHelper::resolveComparator(null));
    }
}
