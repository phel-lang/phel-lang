<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use InvalidArgumentException;
use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\TransientStateTrait;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;

use Stringable;

use function count;

/**
 * @template T
 *
 * @implements TransientVectorInterface<T>
 */
final class TransientVector implements TransientVectorInterface, Stringable
{
    use TransientStateTrait;

    private int $tailSize;

    /**
     * @param int                      $count The number of elements inside this vector
     * @param int                      $shift The shift value
     * @param array<int, array<mixed>> $root  The root node of this vector
     * @param T[]                      $tail  The tail of the vector. This is an optimization
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private int $count,
        private int $shift,
        private array $root,
        private array $tail,
    ) {
        $this->tailSize = count($tail);
    }

    public function __toString(): string
    {
        return '<TransientVector count=' . $this->count . '>';
    }

    /**
     * Lookup by integer index so transient vectors remain callable like their
     * persistent counterparts: `((transient [10 20]) 1) ; => 20`.
     *
     * @return T
     */
    public function __invoke(?int $index)
    {
        if ($index === null) {
            throw new InvalidArgumentException('Vector cannot be indexed with nil');
        }

        return $this->get($index);
    }

    /**
     * @return self<T>
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        return new self(
            $hasher,
            $equalizer,
            0,
            self::SHIFT,
            [],
            [],
        );
    }

    /**
     * @template U
     *
     * @param array<int, U> $array
     *
     * @return self<U>
     */
    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $array): self
    {
        /** @var self<U> $v */
        $v = self::empty($hasher, $equalizer);
        foreach ($array as $a) {
            $v->append($a);
        }

        return $v;
    }

    public function count(): int
    {
        return max(0, $this->count);
    }

    /**
     * @return PersistentVectorInterface<T>
     */
    public function persistent(): PersistentVectorInterface
    {
        $this->invalidateTransient();

        return new PersistentVector($this->hasher, $this->equalizer, null, $this->count, $this->shift, $this->root, $this->tail);
    }

    /**
     * @param T $value
     *
     * @return TransientVectorInterface<T>
     */
    public function append($value): TransientVectorInterface
    {
        $this->ensureTransientActive();

        if ($this->tailSize < self::BRANCH_FACTOR) {
            // There is room for a new value in the tail.
            $this->tail[] = $value;
            ++$this->tailSize;
            ++$this->count;

            return $this;
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

        ++$this->count;
        $this->shift = $newShift;
        $this->root = $newRoot;
        $this->tail = [$value];
        $this->tailSize = 1;

        return $this;
    }

    /**
     * @param T $value
     *
     * @return TransientVectorInterface<T>
     */
    public function update(int $i, $value): TransientVectorInterface
    {
        $this->ensureTransientActive();

        if ($i >= 0 && $i < $this->count) {
            if ($i >= $this->tailOffset()) {
                $this->tail[$i & self::INDEX_MASK] = $value;
            } else {
                $this->updateInPlace($this->shift, $this->root, $i, $value);
            }

            return $this;
        }

        if ($i === $this->count) {
            return $this->append($value);
        }

        throw new IndexOutOfBoundsException('Index out of bounds');
    }

    /**
     * @return T
     */
    public function get(int $i)
    {
        $arr = $this->getArrayForIndex($i);
        return $arr[$i & self::INDEX_MASK];
    }

    /**
     * @return TransientVectorInterface<T>
     */
    public function pop(): TransientVectorInterface
    {
        $this->ensureTransientActive();

        if ($this->count === 0) {
            throw new RuntimeException("Can't pop on empty vector");
        }

        if ($this->count === 1) {
            $this->count = 0;
            return $this;
        }

        $i = $this->count - 1;

        if (($i & self::INDEX_MASK) > 1) {
            --$this->count;
            return $this;
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

        $this->root = $newRoot;
        $this->shift = $newShift;
        --$this->count;
        $this->tail = $newTail;
        $this->tailSize = count($newTail);

        return $this;
    }

    /**
     * @param int $offset
     *
     * @return mixed|null
     */
    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @param int $offset
     */
    public function offsetExists($offset): bool
    {
        return $offset >= 0 && $offset < $this->count;
    }

    public function offsetSet($offset, $value): void
    {
        throw new MethodNotSupportedException('Method offsetSet is not supported on VectorSequence');
    }

    public function offsetUnset($offset): void
    {
        throw new MethodNotSupportedException('Method offsetUnset is not supported on VectorSequence');
    }

    public function contains($key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * @param array<int, mixed> $parent
     * @param array<int, T>     $tailNode
     *
     * @return array<int, mixed>
     */
    private function pushTail(int $level, array $parent, array $tailNode): array
    {
        $ret = $parent;

        if ($level === PersistentVectorInterface::SHIFT) {
            $ret[] = $tailNode;
            return $ret;
        }

        $subIndex = $this->count - 1 >> $level & PersistentVectorInterface::INDEX_MASK;
        if (count($parent) > $subIndex) {
            $ret[$subIndex] = $this->pushTail($level - PersistentVectorInterface::SHIFT, $parent[$subIndex], $tailNode);
            return $ret;
        }

        $ret[] = $this->newPath($level - PersistentVectorInterface::SHIFT, $tailNode);

        return $ret;
    }

    /**
     * @param array<int, mixed> $node
     *
     * @return array<int, mixed>
     */
    private function newPath(int $level, array $node): array
    {
        if ($level === 0) {
            return $node;
        }

        return [$this->newPath($level - self::SHIFT, $node)];
    }

    /**
     * Walks the trie from `$node` (passed by reference) down to the leaf
     * that owns index `$i` and mutates the leaf slot in place. PHP arrays
     * are copy-on-write, so any path nodes still shared with the
     * originating persistent vector get detached automatically on the
     * first mutation at each level; subsequent writes from the same
     * transient mutate directly without re-copying the path. This
     * collapses the per-level array-copy cost the previous `doUpdate`
     * (a persistent-vector path-copy that the persistent `update` uses
     * verbatim) carried on every `assoc!` past the tail offset.
     *
     * @param array<int, mixed> $node
     * @param T                 $value
     */
    private function updateInPlace(int $level, array &$node, int $i, mixed $value): void
    {
        if ($level === 0) {
            $node[$i & self::INDEX_MASK] = $value;
            return;
        }

        $subIndex = ($i >> $level) & self::INDEX_MASK;
        $this->updateInPlace($level - self::SHIFT, $node[$subIndex], $i, $value);
    }

    /**
     * @param array<int, mixed> $node
     *
     * @return array<int, mixed>|null
     */
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

    /**
     * @return list<T>
     */
    private function getArrayForIndex(int $i): array
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

        throw new IndexOutOfBoundsException('Index out of bounds');
    }

    /**
     * Computes the tail offset of this vector based on the count.
     */
    private function tailOffset(): int
    {
        if ($this->count < self::BRANCH_FACTOR) {
            return 0;
        }

        return $this->count - $this->tailSize;
    }
}
