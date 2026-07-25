<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\SortedSet;

use Closure;
use Phel\Lang\Collections\HashSet\AbstractPersistentSet;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\Collections\SortedMap\PersistentSortedMap;
use Phel\Lang\Collections\SortedMap\SortedArrayHelper;

/**
 * @template TValue
 *
 * @extends AbstractPersistentSet<TValue>
 */
final class PersistentSortedSet extends AbstractPersistentSet
{
    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->hasher, $meta, $this->map);
    }

    /**
     * @param TValue $value
     */
    public function add($value): PersistentHashSetInterface
    {
        $newMap = $this->map->put($value, $value);
        if ($newMap === $this->map) {
            return $this;
        }

        /** @var PersistentSortedMap<TValue, TValue> $newMap */
        return new self($this->hasher, $this->meta, $newMap);
    }

    /**
     * @param TValue $value
     */
    public function remove($value): PersistentHashSetInterface
    {
        $newMap = $this->map->remove($value);
        if ($newMap === $this->map) {
            return $this;
        }

        return new self($this->hasher, $this->meta, $newMap);
    }

    /**
     * @return TransientSortedSet<TValue>
     */
    public function asTransient(): TransientSortedSet
    {
        /** @var TransientMapInterface<TValue, TValue> $transient */
        $transient = $this->map->asTransient();
        return new TransientSortedSet($this->hasher, $transient);
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
