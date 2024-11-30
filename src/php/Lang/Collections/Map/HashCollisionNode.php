<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Traversable;

use function array_slice;

/**
 * @template K
 * @template V
 *
 * @implements HashMapNodeInterface<K, V>
 */
final class HashCollisionNode implements HashMapNodeInterface
{
    /**
     * @param array{K,V,K,V} $objects
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly int $hash,
        private readonly int $count,
        private array $objects,
    ) {
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

                return new self($this->hasher, $this->equalizer, $this->hash, $this->count, $this->cloneAndSet($index + 1, $value));
            }

            $addedLeaf->setValue(true);
            return new self($this->hasher, $this->equalizer, $this->hash, $this->count + 1, $this->cloneAndAdd($key, $value));
        }

        $node = new IndexedNode($this->hasher, $this->equalizer, [$this->mask($this->hash, $shift) => [null, $this]]);
        return $node->put($shift, $hash, $key, $value, $addedLeaf);
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

        return new self($this->hasher, $this->equalizer, $this->hash, $this->count - 1, $this->removePair($index));
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

    public function getIterator(): Traversable
    {
        return new HashCollisionNodeIterator($this->objects);
    }

    /**
     * @param K $key
     */
    private function findIndex(mixed $key): int
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
    private function cloneAndSet(int $index, mixed $value): array
    {
        $newObjects = $this->objects;
        $newObjects[$index] = $value;

        return $newObjects;
    }

    /**
     * @param K $key
     * @param V $value
     */
    private function cloneAndAdd(mixed $key, mixed $value): array
    {
        $newObjects = $this->objects;
        $newObjects[] = $key;
        $newObjects[] = $value;

        return $newObjects;
    }

    private function removePair(int $index): array
    {
        return [...array_slice($this->objects, 0, $index), ...array_slice($this->objects, $index + 2)];
    }

    private function mask(int $hash, int $shift): int
    {
        return $hash >> $shift & 0x01f;
    }
}
