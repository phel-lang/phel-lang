<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Collections\Map\MapEntry;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;

use Phel\Lang\HasherInterface;
use Traversable;

use function count;
use function is_object;

/**
 * @template T
 *
 * @implements PersistentVectorInterface<T>
 *
 * @extends AbstractType<PersistentVector<T>>
 */
abstract class AbstractPersistentVector extends AbstractType implements PersistentVectorInterface
{
    /**
     * Keeps the rolling hash accumulator inside a 32-bit unsigned range.
     * `31 * $hash` then never exceeds PHP_INT_MAX, so the accumulator can
     * never silently promote to float (which would throw a TypeError when
     * assigned to the `?int $hashCache` under strict_types — the bug seen
     * with vectors of 13+ elements). 32 bits matches Clojure's hash width
     * and the values produced by `Hasher` (crc32, float bit patterns).
     */
    private const int HASH_MASK = 0xFFFFFFFF;

    private ?int $hashCache = null;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function __construct(
        protected HasherInterface $hasher,
        protected EqualizerInterface $equalizer,
        protected ?PersistentMapInterface $meta,
    ) {}

    /**
     * @return T
     */
    public function __invoke(?int $index)
    {
        if ($index === null) {
            throw new InvalidArgumentException('Vector cannot be indexed with nil');
        }

        return $this->get($index);
    }

    public function first()
    {
        if ($this->count() > 0) {
            return $this->get(0);
        }

        return null;
    }

    /**
     * @return PersistentVectorInterface<T>
     */
    public function rest()
    {
        $cdr = $this->cdr();

        return $cdr ?? PersistentVector::empty($this->hasher, $this->equalizer);
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function hash(): int
    {
        if ($this->hashCache !== null) {
            return $this->hashCache;
        }

        $hash = 1;
        foreach ($this as $obj) {
            $hash = (31 * $hash + $this->hasher->hash($obj)) & self::HASH_MASK;
        }

        return $this->hashCache = $hash;
    }

    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }

        if ($other instanceof PersistentVectorInterface) {
            if ($this->count() !== $other->count()) {
                return false;
            }

            // Walk both vectors in lockstep with their chunk-aware
            // iterators (amortized O(1) per element — the same path
            // `hash()` uses) instead of repeated O(log32 n) `get()`
            // trie descents. The count check above guarantees both
            // iterators stay valid over the same index range.
            $thisIterator = $this->toIterator($this->getIterator());
            $otherIterator = $this->toIterator($other->getIterator());
            while ($thisIterator->valid()) {
                if (!$this->equalizer->equals($thisIterator->current(), $otherIterator->current())) {
                    return false;
                }

                $thisIterator->next();
                $otherIterator->next();
            }

            return true;
        }

        if ($other instanceof MapEntry) {
            return $other->equals($this);
        }

        // Lazy sequences may be infinite — delegate to their own `equals`
        // method which walks both sides pairwise, avoiding eager realization.
        if ($other instanceof LazySeqInterface) {
            return $other->equals($this);
        }

        // Also accept objects with toArray() method (like lazy sequences)
        if (is_object($other) && method_exists($other, 'toArray')) {
            $thisArray = $this->toArray();
            $otherArray = $other->toArray();

            if (count($thisArray) !== count($otherArray)) {
                return false;
            }

            return array_all($thisArray, fn($value, int $i): bool => $this->equalizer->equals($value, $otherArray[$i]));
        }

        return false;
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
        return $offset >= 0 && $offset < $this->count();
    }

    public function offsetSet($offset, $value): void
    {
        throw new MethodNotSupportedException('Method offsetSet is not supported on VectorSequence');
    }

    public function offsetUnset($offset): void
    {
        throw new MethodNotSupportedException('Method offsetUnset is not supported on VectorSequence');
    }

    /**
     * @return PersistentVectorInterface<T>
     */
    public function push(mixed $x): PersistentVectorInterface
    {
        return $this->append($x);
    }

    /**
     * Concatenates a value to the data structure.
     *
     * @param iterable<mixed> $xs The value to concatenate
     *
     * @return PersistentVectorInterface<T>
     */
    public function concat($xs)
    {
        if ($this instanceof PersistentVector) {
            /** @var PersistentVector<T> $self */
            $self = $this;
            $tv = $self->asTransient();
            foreach ($xs as $x) {
                $tv->append($x);
            }

            return $tv->persistent();
        }

        $result = $this;
        foreach ($xs as $x) {
            $result = $result->append($x);
        }

        return $result;
    }

    /**
     * Remove values on a indexed data structures.
     *
     * @param int  $offset The offset where to start to remove values
     * @param ?int $length The number of how many elements should be removed
     */
    /**
     * @return PersistentVectorInterface<T>
     */
    public function slice(int $offset = 0, ?int $length = null): PersistentVectorInterface
    {
        $count = $this->count();

        $normalizedOffset = $offset < 0 ? $count + $offset : $offset;
        $normalizedLength = $length && $length < 0 ? $count + $length : $length;
        if ($normalizedLength === null) {
            $normalizedLength = $count;
        }

        $start = max(0, $normalizedOffset);
        $end = min($start + $normalizedLength, $count);

        if ($start >= $count || $start - $end === 0) {
            /** @var PersistentVector<T> $empty */
            $empty = PersistentVector::empty($this->hasher, $this->equalizer);
            return $empty;
        }

        return $this->sliceNormalized($start, $end);
    }

    public function contains($key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * @return PersistentVectorInterface<T>
     */
    abstract protected function sliceNormalized(int $start, int $end): PersistentVectorInterface;

    /**
     * Unwraps a vector's `getIterator()` result so `valid` / `current` /
     * `next` can be driven directly in the lockstep `equals` walk.
     * `PersistentVector` yields a `RangeIterator`; `SubVector` yields a
     * Generator — both are already `Iterator`s and pass straight through.
     *
     * @param Traversable<mixed, mixed> $traversable
     *
     * @return Iterator<mixed, mixed>
     */
    private function toIterator(Traversable $traversable): Iterator
    {
        while ($traversable instanceof IteratorAggregate) {
            $traversable = $traversable->getIterator();
        }

        /** @var Iterator<mixed, mixed> $traversable */
        return $traversable;
    }
}
