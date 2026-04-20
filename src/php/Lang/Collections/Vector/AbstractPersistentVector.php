<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use InvalidArgumentException;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\LazySeq\LazySeqInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;

use Phel\Lang\HasherInterface;

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
    private int $hashCache = 0;

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
     * @return PersistentVectorInterface
     */
    public function rest()
    {
        $cdr = $this->cdr();

        return $cdr ?? PersistentVector::empty($this->hasher, $this->equalizer);
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function hash(): int
    {
        if ($this->hashCache === 0) {
            $this->hashCache = 1;
            foreach ($this as $obj) {
                $this->hashCache = 31 * $this->hashCache + $this->hasher->hash($obj);
            }
        }

        return $this->hashCache;
    }

    public function equals(mixed $other): bool
    {
        if ($other instanceof PersistentVectorInterface) {
            $count = $this->count();
            if ($count !== $other->count()) {
                return false;
            }

            for ($i = 0; $i < $count; ++$i) {
                if (!$this->equalizer->equals($this->get($i), $other->get($i))) {
                    return false;
                }
            }

            return true;
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

    public function push(mixed $x): PersistentVectorInterface
    {
        return $this->append($x);
    }

    /**
     * Concatenates a value to the data structure.
     *
     * @param mixed[] $xs The value to concatenate
     *
     * @return PersistentVectorInterface
     */
    public function concat($xs)
    {
        if ($this instanceof PersistentVector) {
            $tv = $this->asTransient();
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
            return PersistentVector::empty($this->hasher, $this->equalizer);
        }

        return $this->sliceNormalized($start, $end);
    }

    public function contains($key): bool
    {
        return $this->offsetExists($key);
    }

    abstract protected function sliceNormalized(int $start, int $end): PersistentVectorInterface;
}
