<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;

/**
 * @template V
 *
 * @implements PersistentHashSetInterface<V>
 */
class PersistentHashSet implements PersistentHashSetInterface
{
    /** @var PersistentHashMapInterface<V, V> */
    private $map;

    public function __construct(PersistentHashMapInterface $map)
    {
        $this->map = $map;
    }

    /**
     * @param V $value
     */
    public function contains($value): bool
    {
        return $this->map->containsKey($value);
    }

    /**
     * @param V $value
     */
    public function add($value): PersistentHashSetInterface
    {
        if ($this->contains($value)) {
            return $this;
        }

        return new PersistentHashSet($this->map->put($value, $value));
    }

    /**
     * @param V $value
     */
    public function remove($value): PersistentHashSetInterface
    {
        if ($this->contains($value)) {
            return new PersistentHashSet($this->map->remove($value));
        }

        return $this;
    }

    public function count(): int
    {
        return $this->map->count();
    }
}
