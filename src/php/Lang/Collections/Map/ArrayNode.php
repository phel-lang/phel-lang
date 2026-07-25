<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Countable;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Traversable;

/**
 * @template TKey
 * @template TValue
 *
 * @implements HashMapNodeInterface<TKey, TValue>
 */
final class ArrayNode implements HashMapNodeInterface, Countable
{
    /**
     * @param array<int, ?HashMapNodeInterface<TKey, TValue>> $childNodes A fixed size array of nodes
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly int $count,
        private array $childNodes,
    ) {}

    /**
     * @return self<TKey, TValue>
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        /** @var self<TKey, TValue> $result */
        $result = new self($hasher, $equalizer, 0, []);
        return $result;
    }

    public function count(): int
    {
        return max(0, $this->count);
    }

    /**
     * @param TKey   $key
     * @param TValue $value
     *
     * @return HashMapNodeInterface<TKey, TValue>
     */
    public function put(int $shift, int $hash, $key, $value, Box $addedLeaf): HashMapNodeInterface
    {
        $index = $this->mask($hash, $shift);

        if (isset($this->childNodes[$index])) {
            /** @var HashMapNodeInterface<TKey, TValue> $node */
            $node = $this->childNodes[$index];
            /** @var HashMapNodeInterface<TKey, TValue> $n */
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

        /** @var HashMapNodeInterface<TKey, TValue> $newNode */
        $newNode = IndexedNode::empty($this->hasher, $this->equalizer)->put($shift + 5, $hash, $key, $value, $addedLeaf);

        return new self(
            $this->hasher,
            $this->equalizer,
            $this->count + 1,
            $this->cloneAndSet($index, $newNode),
        );
    }

    /**
     * @param TKey $key
     *
     * @return HashMapNodeInterface<TKey, TValue>
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
     * @param TKey  $key
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
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        /** @var ArrayNodeIterator<TKey, TValue> $iterator */
        $iterator = new ArrayNodeIterator($this->childNodes);
        return $iterator;
    }

    /**
     * @param HashMapNodeInterface<TKey, TValue>|null $node
     *
     * @return array<int, ?HashMapNodeInterface<TKey, TValue>>
     */
    private function cloneAndSet(int $index, ?HashMapNodeInterface $node): array
    {
        $newChildNodes = $this->childNodes;
        $newChildNodes[$index] = $node;

        return $newChildNodes;
    }

    /**
     * @return HashMapNodeInterface<TKey, TValue>
     */
    private function pack(int $index): HashMapNodeInterface
    {
        /** @var array<int, array{0: TKey|null, 1: HashMapNodeInterface<TKey, TValue>|TValue}> $objects */
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

        /**
         * @var IndexedNode<TKey, TValue> $result
         *
         * @psalm-suppress InvalidArgument $objects holds [key, value] and
         * [null, childNode] pairs by trie construction; psalm cannot reconcile
         * the HashMapNodeInterface<TKey, TValue>|TValue element union with IndexedNode's
         * own template parameters (a generic-variance limitation PHPStan accepts).
         */
        $result = new IndexedNode($this->hasher, $this->equalizer, $objects);
        return $result;
    }

    private function mask(int $hash, int $shift): int
    {
        return $hash >> $shift & 0x01f;
    }
}
