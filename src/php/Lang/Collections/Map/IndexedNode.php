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
class IndexedNode implements HashMapNodeInterface
{
    private EqualizerInterface $equalizer;
    private HasherInterface $hasher;
    /** @var array<int, array{0: K|null, 1: V|HashMapNodeInterface<K, V>}> */
    private array $objects;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, array $objects)
    {
        $this->hasher = $hasher;
        $this->equalizer = $equalizer;
        $this->objects = $objects;
    }

    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        return new self($hasher, $equalizer, []);
    }

    /**
     * @param K $key
     * @param V $value
     *
     * @return HashMapNodeInterface<K, V>
     */
    public function put(int $shift, int $hash, $key, $value, Box $addedLeaf): HashMapNodeInterface
    {
        $index = $this->mask($hash, $shift);
        if (isset($this->objects[$index])) {
            [$currentKey, $currentValue] = $this->objects[$index];

            if ($currentKey === null) {
                return $this->addToChild($index, $shift, $hash, $key, $value, $addedLeaf);
            }

            /** @var K $currentKey */
            /** @var V $currentValue */
            if ($this->equalizer->equals($key, $currentKey)) {
                return $this->updateKey($index, $currentValue, $value);
            }

            $addedLeaf->setValue(true);
            $newObjects = $this->objects;
            /** @var K $currentKey */
            /** @var V $currentValue */
            $newObjects[$index] = [null, $this->createNode($shift + 5, $currentKey, $currentValue, $hash, $key, $value)];

            return new IndexedNode($this->hasher, $this->equalizer, $newObjects);
        }

        return $this->insertNewKey($index, $shift, $hash, $key, $value, $addedLeaf);
    }

    /**
     * @param K $key1
     * @param V $value1
     * @param K $key2
     * @param V $value2
     *
     * @return HashMapNodeInterface<K, V>
     */
    private function createNode(int $shift, $key1, $value1, int $key2Hash, $key2, $value2): HashMapNodeInterface
    {
        $key1Hash = $this->hasher->hash($key1);
        if ($key1Hash === $key2Hash) {
            return new HashCollisionNode($this->hasher, $this->equalizer, $key1Hash, 2, [$key1, $value1, $key2, $value2]);
        }

        $addedLeaf = new Box(null);
        return IndexedNode::empty($this->hasher, $this->equalizer)
            ->put($shift, $key1Hash, $key1, $value1, $addedLeaf)
            ->put($shift, $key2Hash, $key2, $value2, $addedLeaf);
    }

    /**
     * @param V $currentValue
     * @param V $newValue
     *
     * @return HashMapNodeInterface<K, V>
     */
    private function updateKey(int $index, $currentValue, $newValue): HashMapNodeInterface
    {
        if ($this->equalizer->equals($newValue, $currentValue)) {
            return $this;
        }

        $newObjects = $this->objects;
        $newObjects[$index][1] = $newValue;
        return new IndexedNode($this->hasher, $this->equalizer, $newObjects);
    }

    /**
     * @param K $key
     * @param V $value
     *
     * @return HashMapNodeInterface<K, V>
     */
    private function addToChild(int $idx, int $shift, int $hash, $key, $value, Box $addedLeaf): HashMapNodeInterface
    {
        /** @var HashMapNodeInterface $node */
        $childNode = $this->objects[$idx][1];
        $newChild = $childNode->put($shift + 5, $hash, $key, $value, $addedLeaf);
        if ($childNode === $newChild) {
            // Nothing changed
            return $this;
        }

        $newObjects = $this->objects;
        $newObjects[$idx][1] = $newChild;
        return new IndexedNode($this->hasher, $this->equalizer, $newObjects);
    }

    /**
     * @param K $key
     * @param V $value
     *
     * @return HashMapNodeInterface<K, V>
     */
    private function insertNewKey(int $idx, int $shift, int $hash, $key, $value, Box $addedLeaf): HashMapNodeInterface
    {
        if (count($this->objects) >= 16) {
            return $this->splitNode($idx, $shift, $hash, $key, $value, $addedLeaf);
        }

        return $this->addNewKeyToNode($idx, $key, $value, $addedLeaf);
    }

    /**
     * @param K $key
     * @param V $value
     *
     * @return HashMapNodeInterface<K, V>
     */
    private function splitNode(int $idx, int $shift, int $hash, $key, $value, Box $addedLeaf): HashMapNodeInterface
    {
        $nodes = []; //array_fill(0, 32, null);
        $empty = IndexedNode::empty($this->hasher, $this->equalizer);
        $nodes[$idx] = $empty->put($shift + 5, $hash, $key, $value, $addedLeaf);
        for ($i = 0; $i < 32; $i++) {
            if (array_key_exists($i, $this->objects)) {
                [$k, $v] = $this->objects[$i];
                if ($k === null) {
                    /** @var HashMapNodeInterface<K, V> $v */
                    $nodes[$i] = $v;
                } else {
                    /** @var V $v */
                    $nodes[$i] = $empty->put($shift + 5, $this->hasher->hash($k), $k, $v, $addedLeaf);
                }
            }
        }

        return new ArrayNode($this->hasher, $this->equalizer, count($this->objects) + 1, $nodes);
    }

    /**
     * @param K $key
     * @param V $value
     *
     * @return IndexedNode<K, V>
     */
    private function addNewKeyToNode(int $idx, $key, $value, Box $addedLeaf): IndexedNode
    {
        $newObjects = $this->objects;
        $newObjects[$idx] = [$key, $value];
        $addedLeaf->setValue(true);
        return new IndexedNode($this->hasher, $this->equalizer, $newObjects);
    }

    /**
     * @param mixed $key
     */
    public function remove(int $shift, int $hash, $key): ?HashMapNodeInterface
    {
        $index = $this->mask($hash, $shift);
        if (!isset($this->objects[$index])) {
            return $this;
        }

        [$currentKey, $currentValue] = $this->objects[$index];

        if ($currentKey === null) {
            /** @var HashMapNodeInterface $node */
            $node = $currentValue;
            $n = $node->remove($shift + 5, $hash, $key);

            if ($n === $node) {
                return $this;
            }

            if ($n !== null) {
                $newObjects = $this->objects;
                $newObjects[$index][1] = $n;
                return new IndexedNode($this->hasher, $this->equalizer, $newObjects);
            }

            if (count($this->objects) === 1) {
                return null;
            }

            $newObjects = $this->objects;
            unset($newObjects[$index]);
            return new IndexedNode($this->hasher, $this->equalizer, $newObjects);
        }

        if ($this->equalizer->equals($key, $currentKey)) {
            if (count($this->objects) === 1) {
                return null;
            }

            $newObjects = $this->objects;
            unset($newObjects[$index]);
            return new IndexedNode($this->hasher, $this->equalizer, $newObjects);
        }

        return $this;
    }

    /**
     * @param mixed $key
     * @param mixed $notFound
     *
     * @return ?mixed
     */
    public function find(int $shift, int $hash, $key, $notFound)
    {
        $index = $this->mask($hash, $shift);
        if (!isset($this->objects[$index])) {
            return $notFound;
        }

        [$currentKey, $currentValue] = $this->objects[$index];

        if ($currentKey === null) {
            /** @var HashMapNodeInterface $node */
            $node = $currentValue;

            return $node->find($shift + 5, $hash, $key, $notFound);
        }

        if ($this->equalizer->equals($key, $currentKey)) {
            return $currentValue;
        }

        return $notFound;
    }

    private function mask(int $hash, int $shift): int
    {
        return $hash >> $shift & 0x01f;
    }

    public function getIterator(): Traversable
    {
        return new IndexedNodeIterator($this->objects);
    }
}
