<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;

/**
 * @template T
 * @implements PersistentVectorInterface<T>
 * @extends AbstractType<PersistentVector<T>>
 */
abstract class AbstractPersistentVector extends AbstractType implements PersistentVectorInterface
{
    protected EqualizerInterface $equalizer;
    protected HasherInterface $hasher;
    protected ?PersistentMapInterface $meta;
    private int $hashCache = 0;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentMapInterface $meta)
    {
        $this->equalizer = $equalizer;
        $this->hasher = $hasher;
        $this->meta = $meta;
    }

    /**
     * @param int $index
     *
     * @return T
     */
    public function __invoke(int $index)
    {
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
        if ($cdr === null) {
            return PersistentVector::empty($this->hasher, $this->equalizer);
        }

        return $cdr;
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

    public function equals($other): bool
    {
        if (!$other instanceof PersistentVectorInterface) {
            return false;
        }

        if ($this->count() !== $other->count()) {
            return false;
        }

        $s = $this;
        $ms = $other;
        for ($s = $this; $s != null; $s = $s->cdr(), $ms = $ms->cdr()) {
            /** @var PersistentVectorInterface $s */
            /** @var ?PersistentVectorInterface $ms */
            if ($ms === null || !$this->equalizer->equals($s->first(), $ms->first())) {
                return false;
            }
        }

        return true;
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

    public function push($x)
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
        $result = $this;
        foreach ($xs as $x) {
            $result = $result->append($x);
        }

        return $result;
    }

    /**
     * Remove values on a indexed data structures.
     *
     * @param int $offset The offset where to start to remove values
     * @param ?int $length The number of how many elements should be removed
     *
     * @return PersistentVectorInterface
     */
    public function slice(int $offset = 0, ?int $length = null)
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

    abstract protected function sliceNormalized(int $start, int $end): PersistentVectorInterface;

    public function contains($key): bool
    {
        return $this->offsetExists($key);
    }
}
