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
final class ArrayNode implements HashMapNodeInterface, Countable
{
    /**
     * @param list<?HashMapNodeInterface<K, V>> $childNodes A fixed size array of nodes
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly int $count,
        private array $childNodes,
    ) {}

    /**
     * @return self<K, V>
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        return new self($hasher, $equalizer, 0, []);
    }

    public function count(): int
    {
        return max(0, $this->count);
    }

    /**
     * @param mixed $key
     * @param mixed $value
     *
     * @return HashMapNodeInterface<K, V>
     */
    public function put(int $shift, int $hash, $key, $value, Box $addedLeaf): HashMapNodeInterface
    {
        $index = $this->mask($hash, $shift);

        if (isset($this->childNodes[$index])) {
            /** @var HashMapNodeInterface<K, V> $node */
            $node = $this->childNodes[$index];
            $n = $node->put($shift + 5, $hash, $key, $value, $addedLeaf);
            if ($n === $node) {
                return $this;
            }

            return new self(
                $this->hasher,
                $this->equalizer,
                $this->count,
                $this->cloneAndSet($index, $n),
            );
        }

        return new self(
            $this->hasher,
            $this->equalizer,
            $this->count + 1,
            $this->cloneAndSet($index, IndexedNode::empty($this->hasher, $this->equalizer)->put($shift + 5, $hash, $key, $value, $addedLeaf)),
        );
    }

    /**
     * @param mixed $key
     *
     * @return HashMapNodeInterface<K, V>
     */
    public function remove(int $shift, int $hash, $key): HashMapNodeInterface
    {
        $index = $this->mask($hash, $shift);
        $node = $this->childNodes[$index] ?? null;

        if (!$node instanceof HashMapNodeInterface) {
            return $this;
        }

        $n = $node->remove($shift + 5, $hash, $key);

        if ($n === $node) {
            return $this;
        }

        if (!$n instanceof HashMapNodeInterface) {
            if ($this->count < 8) {
                return $this->pack($index);
            }

            return new self($this->hasher, $this->equalizer, $this->count - 1, $this->cloneAndSet($index, $n));
        }

        return new self($this->hasher, $this->equalizer, $this->count, $this->cloneAndSet($index, $n));
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

        if (!$node instanceof HashMapNodeInterface) {
            return $notFound;
        }

        return $node->find($shift + 5, $hash, $key, $notFound);
    }

    /**
     * @return Traversable<K, V>
     */
    public function getIterator(): Traversable
    {
        return new ArrayNodeIterator($this->childNodes);
    }

    /**
     * @param HashMapNodeInterface<K, V>|null $node
     *
     * @return list<?HashMapNodeInterface<K, V>>
     */
    private function cloneAndSet(int $index, ?HashMapNodeInterface $node): array
    {
        $newChildNodes = $this->childNodes;
        $newChildNodes[$index] = $node;

        return $newChildNodes;
    }

    /**
     * @return HashMapNodeInterface<K, V>
     */
    private function pack(int $index): HashMapNodeInterface
    {
        /** @var list<array{0: K|null, 1: HashMapNodeInterface<K, V>|V}> $objects */
        $objects = [];
        foreach ($this->childNodes as $i => $node) {
            if ($i === $index) {
                continue;
            }

            if (!$node instanceof HashMapNodeInterface) {
                continue;
            }

            $objects[$i] = [null, $node];
        }

        /** @var IndexedNode<K, V> $result */
        $result = new IndexedNode($this->hasher, $this->equalizer, $objects);
        return $result;
    }

    private function mask(int $hash, int $shift): int
    {
        return $hash >> $shift & 0x01f;
    }
}
