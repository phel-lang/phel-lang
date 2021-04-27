<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Traversable;

/**
 * An implementation of a persistent vector inspiered
 * by the Clojure implementation of Rich Hickey.
 *
 * The formal name of this vector is 'persistent
 * bit-partitioned vector trie'.
 *
 * A good introduction to this datastructure can be found here:
 * * https://hypirion.com/musings/understanding-persistent-vector-pt-1
 * * https://hypirion.com/musings/understanding-persistent-vector-pt-2
 * * https://hypirion.com/musings/understanding-persistent-vector-pt-3
 * * https://hypirion.com/musings/understanding-clojure-transients
 * * https://hypirion.com/musings/persistent-vector-performance-summarised
 *
 * @template T
 * @extends AbstractPersistentVector<T>
 */
class PersistentVector extends AbstractPersistentVector
{
    /** @var int The number of elements stored in this vector */
    private int $count;
    private int $shift;
    /** @var array<array> The root node of this vector */
    private array $root;
    /** @var array<int, T> The tail of the vector. This is an optimization */
    private array $tail;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentMapInterface $meta, int $count, int $shift, array $root, array $tail)
    {
        parent::__construct($hasher, $equalizer, $meta);
        $this->count = $count;
        $this->shift = $shift;
        $this->root = $root;
        $this->tail = $tail;
    }

    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        return new self($hasher, $equalizer, null, 0, self::SHIFT, [], []);
    }

    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $values): PersistentVectorInterface
    {
        $tv = TransientVector::empty($hasher, $equalizer);
        foreach ($values as $value) {
            $tv->append($value);
        }

        return $tv->persistent();
    }

    public function withMeta(?PersistentMapInterface $meta)
    {
        return new PersistentVector($this->hasher, $this->equalizer, $meta, $this->count, $this->shift, $this->root, $this->tail);
    }

    /**
     * Return the number of elements in this vector.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Appends a value to the vector.
     *
     * Appends are not too much different from updates,
     * except that we have some edge cases where we have to generate nodes in order
     * to fit in a value. Essentially, there are three cases:
     * 1. There is room for a new value in the tail.
     * 2. There is space in the root, but not in the tail.
     * 3. There is not enough space in the current root.
     * (Source: https://hypirion.com/musings/understanding-persistent-vector-pt-1)
     *
     * @param T $value
     *
     * @return PersistentVector
     */
    public function append($value): PersistentVector
    {
        if (count($this->tail) < self::BRANCH_FACTOR) {
            // There is room for a new value in the tail.
            return new PersistentVector(
                $this->hasher,
                $this->equalizer,
                $this->meta,
                $this->count + 1,
                $this->shift,
                $this->root,
                [...$this->tail, $value]
            );
        }

        // Tail if full, push into tree
        $tailNode = $this->tail;
        $newShift = $this->shift;
        if ($this->count >> self::SHIFT > (1 << $this->shift)) {
            // overflow root
            $newRoot = [$this->root, $this->newPath($this->shift, $tailNode)];
            $newShift += self::SHIFT;
        } else {
            $newRoot = $this->pushTail($this->shift, $this->root, $tailNode);
        }

        return new PersistentVector(
            $this->hasher,
            $this->equalizer,
            $this->meta,
            $this->count + 1,
            $newShift,
            $newRoot,
            [$value]
        );
    }

    private function pushTail(int $level, array $parent, array $tailNode): array
    {
        $ret = $parent;

        if ($level === self::SHIFT) {
            $ret[] = $tailNode;
            return $ret;
        }

        $subIndex = $this->count - 1 >> $level & self::INDEX_MASK;
        if (count($parent) > $subIndex) {
            $ret[$subIndex] = $this->pushTail($level - self::SHIFT, $parent[$subIndex], $tailNode);
            return $ret;
        }

        $ret[] = $this->newPath($level - self::SHIFT, $tailNode);

        return $ret;
    }

    private function newPath(int $level, array $node): array
    {
        if ($level === 0) {
            return $node;
        }

        return [$this->newPath($level - self::SHIFT, $node)];
    }

    /**
     * Updates the value at position $i with new new value.
     *
     * To update an element, we would have to walk the tree down
     * to the leaf node where the element is placed. While we walk down,
     * we copy the nodes on our path to ensure persistence. When we’ve gotten
     * down to the leaf node, we copy it and replace the value we wanted to
     * replace with the new value. We then return the new vector with the modified path.
     * (Source: https://hypirion.com/musings/understanding-persistent-vector-pt-1)
     *
     * @param int $i the index in the vector
     * @param T $value The new value
     *
     * @return PersistentVector
     */
    public function update(int $i, $value): PersistentVector
    {
        if ($i >= 0 && $i < $this->count) {
            if ($i >= $this->tailOffset()) {
                $newTail = $this->tail;
                $newTail[$i & self::INDEX_MASK] = $value;

                return new PersistentVector(
                    $this->hasher,
                    $this->equalizer,
                    $this->meta,
                    $this->count,
                    $this->shift,
                    $this->root,
                    $newTail
                );
            }

            return new PersistentVector(
                $this->hasher,
                $this->equalizer,
                $this->meta,
                $this->count,
                $this->shift,
                $this->doUpdate($this->shift, $this->root, $i, $value),
                $this->tail
            );
        }

        if ($i === $this->count) {
            return $this->append($value);
        }

        throw new IndexOutOfBoundsException('Index out of bounds');
    }

    /**
     * @param T $value
     */
    private function doUpdate(int $level, array $node, int $i, $value): array
    {
        $ret = $node;
        if ($level === 0) {
            $ret[$i & self::INDEX_MASK] = $value;
        } else {
            $subIndex = ($i >> $level) & self::INDEX_MASK;
            $ret[$subIndex] = $this->doUpdate($level - self::SHIFT, $node[$subIndex], $i, $value);
        }

        return $ret;
    }

    /**
     * Gets the value at index $i.
     *
     * @param int $i The index
     *
     * @return T
     */
    public function get(int $i)
    {
        $arr = $this->getArrayForIndex($i);
        return $arr[$i & self::INDEX_MASK];
    }

    public function getArrayForIndex(int $i): array
    {
        if ($i >= 0 && $i < $this->count) {
            if ($i >= $this->tailOffset()) {
                return $this->tail;
            }

            $node = $this->root;
            for ($level = $this->shift; $level > 0; $level -= self::SHIFT) {
                $node = $node[($i >> $level) & self::INDEX_MASK];
            }

            return $node;
        }

        throw new IndexOutOfBoundsException("Index $i is not in interval [0, {$this->count})");
    }

    /**
     * Removes the last element from the vector and returns the new vector.
     *
     * The solutions for popping isn’t that difficult to grasp either. Popping
     * is similar to appending in that there are three cases:
     * 1. The tail contains more than one element.
     * 2. The tail contains exactly one element (zero after popping).
     * 3. The root node contains exactly one element after popping.
     *
     * @return PersistentVector
     */
    public function pop(): PersistentVector
    {
        if ($this->count === 0) {
            throw new \RuntimeException("Can't pop on empty vector");
        }

        if ($this->count === 1) {
            return self::empty(
                $this->hasher,
                $this->equalizer,
            );
        }

        if ($this->count - $this->tailOffset() > 1) {
            $newTail = array_slice($this->tail, 0, -1);
            return new PersistentVector(
                $this->hasher,
                $this->equalizer,
                $this->meta,
                $this->count - 1,
                $this->shift,
                $this->root,
                $newTail
            );
        }

        $newTail = $this->getArrayForIndex($this->count - 2);

        $newRoot = $this->popTail($this->shift, $this->root);
        $newShift = $this->shift;
        if ($newRoot === null) {
            $newRoot = [];
        }

        if ($this->shift > self::SHIFT && $newRoot[1] === null) {
            $newRoot = $newRoot[0];
            $newShift -= self::SHIFT;
        }

        return new PersistentVector(
            $this->hasher,
            $this->equalizer,
            $this->meta,
            $this->count - 1,
            $newShift,
            $newRoot,
            $newTail
        );
    }

    private function popTail(int $level, array $node): ?array
    {
        $subIndex = ($this->count - 2 >> $level) & self::INDEX_MASK;
        if ($level > self::SHIFT) {
            $newChild = $this->popTail($level - self::SHIFT, $node[$subIndex]);
            if ($newChild === null && $subIndex === 0) {
                return null;
            }

            $ret = $node;
            $ret[$subIndex] = $newChild;
            return $ret;
        }

        if ($subIndex === 0) {
            return null;
        }

        $ret = $node;
        $ret[$subIndex] = null;

        return $ret;
    }

    public function toArray(): array
    {
        $result = [];
        $this->fillArray($this->root, $this->shift, $result);
        $result[] = $this->tail;
        return array_merge(...$result);
    }

    private function fillArray(array $node, int $shift, array &$targetArr = []): void
    {
        if ($shift) {
            $shift -= self::SHIFT;
            foreach ($node as $x) {
                $this->fillArray($x, $shift, $targetArr);
            }
        } else {
            $targetArr[] = $node;
        }
    }

    public function getIterator(): Traversable
    {
        return $this->getRangeIterator(0, $this->count);
    }

    public function getRangeIterator(int $start, int $end): Traversable
    {
        return new RangeIterator($this, $start, $end);
    }

    /**
     * Computes the tail offset of this vector based on the count.
     */
    private function tailOffset(): int
    {
        if ($this->count < self::BRANCH_FACTOR) {
            return 0;
        }

        return $this->count - count($this->tail);
    }

    /**
     * @return PersistentVectorInterface|null
     */
    public function cdr()
    {
        if ($this->count() <= 1) {
            return null;
        }

        return new SubVector($this->hasher, $this->equalizer, $this->meta, $this, 1, $this->count());
    }

    public function sliceNormalized(int $start, int $end): PersistentVectorInterface
    {
        return new SubVector($this->hasher, $this->equalizer, $this->meta, $this, $start, $end);
    }

    public function asTransient(): TransientVector
    {
        return new TransientVector(
            $this->hasher,
            $this->equalizer,
            $this->count,
            $this->shift,
            $this->root,
            $this->tail
        );
    }
}
