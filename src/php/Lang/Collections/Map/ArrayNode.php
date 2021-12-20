<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Countable;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Traversable;

/**
 * @template K
 * @template V
 *
 * @implements HashMapNodeInterface<K, V>
 */
class ArrayNode implements HashMapNodeInterface, Countable
{
    private HasherInterface $hasher;
    private EqualizerInterface $equalizer;
    private int $count;
    /** @var array<int, ?HashMapNodeInterface<K, V>> A fixed size array of nodes */
    private array $childNodes;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, int $count, array $childNodes)
    {
        $this->hasher = $hasher;
        $this->equalizer = $equalizer;
        $this->count = $count;
        $this->childNodes = $childNodes;
    }

    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        return new self($hasher, $equalizer, 0, []);
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function put(int $shift, int $hash, $key, $value, Box $addedLeaf): HashMapNodeInterface
    {
        $index = $this->mask($hash, $shift);

        if (isset($this->childNodes[$index])) {
            /** @var HashMapNodeInterface $node */
            $node = $this->childNodes[$index];
            $n = $node->put($shift + 5, $hash, $key, $value, $addedLeaf);
            if ($n == $node) {
                return $this;
            }

            return new ArrayNode(
                $this->hasher,
                $this->equalizer,
                $this->count,
                $this->cloneAndSet($index, $n)
            );
        }

        return new ArrayNode(
            $this->hasher,
            $this->equalizer,
            $this->count + 1,
            $this->cloneAndSet($index, IndexedNode::empty($this->hasher, $this->equalizer)->put($shift + 5, $hash, $key, $value, $addedLeaf))
        );
    }

    private function cloneAndSet(int $index, ?HashMapNodeInterface $node): array
    {
        $newChildNodes = $this->childNodes;
        $newChildNodes[$index] = $node;

        return $newChildNodes;
    }

    public function remove(int $shift, int $hash, $key): ?HashMapNodeInterface
    {
        $index = $this->mask($hash, $shift);
        $node = $this->childNodes[$index] ?? null;

        if ($node === null) {
            return $this;
        }

        $n = $node->remove($shift + 5, $hash, $key);

        if ($n == $node) {
            return $this;
        }

        if ($n === null) {
            if ($this->count < 8) {
                return $this->pack($index);
            }

            return new ArrayNode($this->hasher, $this->equalizer, $this->count - 1, $this->cloneAndSet($index, $n));
        }

        return new ArrayNode($this->hasher, $this->equalizer, $this->count, $this->cloneAndSet($index, $n));
    }

    private function pack(int $index): HashMapNodeInterface
    {
        $objects = [];
        foreach ($this->childNodes as $i => $node) {
            if ($i !== $index && $node !== null) {
                $objects[$i] = [null, $node];
            }
        }

        return new IndexedNode($this->hasher, $this->equalizer, $objects);
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
        $node = $this->childNodes[$index] ?? null;

        if ($node === null) {
            return $notFound;
        }

        return $node->find($shift + 5, $hash, $key, $notFound);
    }

    private function mask(int $hash, int $shift): int
    {
        return $hash >> $shift & 0x01f;
    }

    public function getIterator(): Traversable
    {
        return new ArrayNodeIterator($this->childNodes);
    }
}
