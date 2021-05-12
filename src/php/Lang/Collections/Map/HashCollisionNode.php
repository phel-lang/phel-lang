<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Traversable;

/**
 * @template K
 * @template V
 *
 * @implements HashMapNodeInterface<K, V>
 */
class HashCollisionNode implements HashMapNodeInterface
{
    private HasherInterface $hasher;
    private EqualizerInterface $equalizer;
    private int $hash;
    private int $count;
    /** @var array<int, K|V> */
    private array $objects;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, int $hash, int $count, array $objects)
    {
        $this->hasher = $hasher;
        $this->equalizer = $equalizer;
        $this->hash = $hash;
        $this->count = $count;
        $this->objects = $objects;
    }

    /**
     * @param K $key
     * @param V $value
     */
    public function put(int $shift, int $hash, $key, $value, Box $addedLeaf): HashMapNodeInterface
    {
        if ($hash === $this->hash) {
            $index = $this->findIndex($key);
            if ($index !== -1) {
                if ($this->equalizer->equals($this->objects[$index + 1], $value)) {
                    return $this;
                }

                return new HashCollisionNode($this->hasher, $this->equalizer, $this->hash, $this->count, $this->cloneAndSet($index + 1, $value));
            }

            $addedLeaf->setValue(true);
            return new HashCollisionNode($this->hasher, $this->equalizer, $this->hash, $this->count + 1, $this->cloneAndAdd($key, $value));
        }

        $node = new IndexedNode($this->hasher, $this->equalizer, [$this->mask($this->hash, $shift) => [null, $this]]);
        return $node->put($shift, $hash, $key, $value, $addedLeaf);
    }

    /**
     * @param K $key
     */
    private function findIndex($key): int
    {
        for ($i = 0; $i < 2 * $this->count; $i += 2) {
            if ($this->equalizer->equals($key, $this->objects[$i])) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * @param V $value
     */
    private function cloneAndSet(int $index, $value): array
    {
        $newObjects = $this->objects;
        $newObjects[$index] = $value;

        return $newObjects;
    }

    /**
     * @param K $key
     * @param V $value
     */
    private function cloneAndAdd($key, $value): array
    {
        $newObjects = $this->objects;
        $newObjects[] = $key;
        $newObjects[] = $value;

        return $newObjects;
    }

    public function remove(int $shift, int $hash, $key): ?HashMapNodeInterface
    {
        $index = $this->findIndex($key);
        if ($index === -1) {
            return $this;
        }

        if ($this->count === 1) {
            return null;
        }

        return new HashCollisionNode($this->hasher, $this->equalizer, $this->hash, $this->count - 1, $this->removePair($index));
    }

    private function removePair(int $index): array
    {
        return [...array_slice($this->objects, 0, $index), ...array_slice($this->objects, $index + 2)];
    }

    public function find(int $shift, int $hash, $key, $notFound)
    {
        $index = $this->findIndex($key);
        if ($index === -1) {
            return $notFound;
        }

        /** @var V $value */
        $value = $this->objects[$index + 1];

        return $value;
    }

    private function mask(int $hash, int $shift): int
    {
        return $hash >> $shift & 0x01f;
    }

    public function getIterator(): Traversable
    {
        return new HashCollisionNodeIterator($this->objects);
    }
}
