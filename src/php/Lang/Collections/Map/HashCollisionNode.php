<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Traversable;

use function array_slice;
use function array_values;

/**
 * @template K
 * @template V
 *
 * @implements HashMapNodeInterface<K, V>
 */
final class HashCollisionNode implements HashMapNodeInterface
{
    /**
     * @param list<K|V> $objects
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly int $hash,
        private readonly int $count,
        private array $objects,
    ) {}

    /**
     * @param K $key
     * @param V $value
     *
     * @return HashMapNodeInterface<K, V>
     */
    public function put(int $shift, int $hash, $key, $value, Box $addedLeaf): HashMapNodeInterface
    {
        if ($hash === $this->hash) {
            $index = $this->findIndex($key);
            if ($index !== -1) {
                $existingPair = array_slice($this->objects, $index, 2);
                if ($this->equalizer->equals($existingPair[1] ?? null, $value)) {
                    return $this;
                }

                /** @var self<K, V> $updated */
                $updated = new self($this->hasher, $this->equalizer, $this->hash, $this->count, $this->cloneAndSet($index + 1, $value));
                return $updated;
            }

            $addedLeaf->setValue(true);
            /** @var self<K, V> $added */
            $added = new self($this->hasher, $this->equalizer, $this->hash, $this->count + 1, $this->cloneAndAdd($key, $value));
            return $added;
        }

        /** @var array<int, array{0: K|null, 1: HashMapNodeInterface<K, V>|V}> $childObjects */
        $childObjects = [$this->mask($this->hash, $shift) => [null, $this]];
        /**
         * @var IndexedNode<K, V> $node
         *
         * @psalm-suppress InvalidArgument $childObjects holds a [null, childNode]
         * pair by trie construction; psalm cannot reconcile the
         * HashMapNodeInterface<K, V>|V element union with IndexedNode's own
         * template parameters (a generic-variance limitation PHPStan accepts).
         */
        $node = new IndexedNode($this->hasher, $this->equalizer, $childObjects);
        return $node->put($shift, $hash, $key, $value, $addedLeaf);
    }

    /**
     * @param mixed $key
     *
     * @return HashMapNodeInterface<K, V>|null
     */
    public function remove(int $shift, int $hash, $key): ?HashMapNodeInterface
    {
        $index = $this->findIndex($key);
        if ($index === -1) {
            return $this;
        }

        if ($this->count === 1) {
            return null;
        }

        /** @var self<K, V> $result */
        $result = new self($this->hasher, $this->equalizer, $this->hash, $this->count - 1, $this->removePair($index));
        return $result;
    }

    /**
     * @param mixed $key
     * @param mixed $notFound
     *
     * @return ?mixed
     */
    public function find(int $shift, int $hash, $key, $notFound)
    {
        $index = $this->findIndex($key);
        if ($index === -1) {
            return $notFound;
        }

        $pair = array_slice($this->objects, $index, 2);
        /** @var V $value */
        $value = $pair[1] ?? $notFound;

        return $value;
    }

    /**
     * @return Traversable<K, V>
     */
    public function getIterator(): Traversable
    {
        /** @var array{K, V, K, V} $entries */
        $entries = $this->objects;
        /** @var HashCollisionNodeIterator<K, V> $iterator */
        $iterator = new HashCollisionNodeIterator($entries);
        return $iterator;
    }

    /**
     * @param K $key
     */
    private function findIndex(mixed $key): int
    {
        for ($i = 0; $i < 2 * $this->count; $i += 2) {
            if ($this->equalizer->equalsKey($key, $this->objects[$i])) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * @param V $value
     *
     * @return list<K|V>
     */
    private function cloneAndSet(int $index, mixed $value): array
    {
        $newObjects = $this->objects;
        $newObjects[$index] = $value;

        return array_values($newObjects);
    }

    /**
     * @param K $key
     * @param V $value
     *
     * @return list<K|V>
     */
    private function cloneAndAdd(mixed $key, mixed $value): array
    {
        $newObjects = $this->objects;
        $newObjects[] = $key;
        $newObjects[] = $value;

        return $newObjects;
    }

    /**
     * @return list<K|V>
     */
    private function removePair(int $index): array
    {
        return [...array_slice($this->objects, 0, $index), ...array_slice($this->objects, $index + 2)];
    }

    private function mask(int $hash, int $shift): int
    {
        return $hash >> $shift & 0x01f;
    }
}
