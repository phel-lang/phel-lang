<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\HashSet;

use IteratorAggregate;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;
use Phel\Lang\HasherInterface;
use Traversable;

/**
 * @template V
 *
 * @implements PersistentHashSetInterface<V>
 * @extends AbstractType<PersistentHashSet<V>>
 */
class PersistentHashSet extends AbstractType implements PersistentHashSetInterface, IteratorAggregate
{
    private HasherInterface $hasher;
    private ?PersistentHashMapInterface $meta;
    /** @var PersistentHashMapInterface<V, V> */
    private PersistentHashMapInterface $map;
    private int $hashCache = 0;

    public function __construct(HasherInterface $hasher, ?PersistentHashMapInterface $meta, PersistentHashMapInterface $map)
    {
        $this->hasher = $hasher;
        $this->meta = $meta;
        $this->map = $map;
    }

    /**
     * @param V $key
     *
     * @return ?V
     */
    public function __invoke($key)
    {
        return $this->map->find($key);
    }

    public function getMeta(): ?PersistentHashMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentHashMapInterface $meta)
    {
        return new PersistentHashSet($this->hasher, $meta, $this->map);
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

        return new PersistentHashSet($this->hasher, $this->meta, $this->map->put($value, $value));
    }

    /**
     * @param V $value
     */
    public function remove($value): PersistentHashSetInterface
    {
        if ($this->contains($value)) {
            return new PersistentHashSet($this->hasher, $this->meta, $this->map->remove($value));
        }

        return $this;
    }

    public function count(): int
    {
        return $this->map->count();
    }

    public function equals($other): bool
    {
        if (!$other instanceof PersistentHashSet) {
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
}
