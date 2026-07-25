<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Phel\Lang\Collections\Map\PersistentMapInterface;

/**
 * @template TValue
 *
 * @extends AbstractPersistentSet<TValue>
 */
final class PersistentHashSet extends AbstractPersistentSet
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
     *
     * @return PersistentHashSetInterface<TValue>
     */
    public function add($value): PersistentHashSetInterface
    {
        $newMap = $this->map->put($value, $value);
        if ($newMap === $this->map) {
            return $this;
        }

        return new self($this->hasher, $this->meta, $newMap);
    }

    /**
     * @param TValue $value
     *
     * @return PersistentHashSetInterface<TValue>
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
     * @return TransientHashSet<TValue>
     */
    public function asTransient(): TransientHashSet
    {
        return new TransientHashSet($this->hasher, $this->map->asTransient());
    }
}
