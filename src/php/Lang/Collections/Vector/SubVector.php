<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Traversable;

/**
 * @template T
 * @extends AbstractPersistentVector<T>
 */
class SubVector extends AbstractPersistentVector
{
    private PersistentVectorInterface $vector;
    private int $start;
    private int $end;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentMapInterface $meta, PersistentVectorInterface $vector, int $start, int $end)
    {
        parent::__construct($hasher, $equalizer, $meta);
        $this->vector = $vector;
        $this->start = $start;
        $this->end = $end;
    }

    public function count(): int
    {
        return $this->end - $this->start;
    }

    /**
     * @return PersistentVectorInterface|null
     */
    public function cdr()
    {
        if ($this->start + 1 < $this->end) {
            return new SubVector($this->hasher, $this->equalizer, $this->meta, $this->vector, $this->start + 1, $this->end);
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        return array_slice($this->vector->toArray(), $this->start, $this->end - $this->start);
    }

    public function withMeta(?PersistentMapInterface $meta)
    {
        return new SubVector($this->hasher, $this->equalizer, $meta, $this->vector, $this->start, $this->end);
    }

    /**
     * @return Traversable<T>
     */
    public function getIterator(): Traversable
    {
        for ($s = $this; $s != null; $s = $s->cdr()) {
            /** @var PersistentList<T> $s */
            /** @var T $first  */
            $first = $s->first();
            yield $first;
        }
    }

    /**
     * @param T $value
     */
    public function append($value): PersistentVectorInterface
    {
        return new SubVector($this->hasher, $this->equalizer, $this->meta, $this->vector->update($this->end, $value), $this->start, $this->end + 1);
    }

    /**
     * @param T $value
     */
    public function update(int $i, $value): PersistentVectorInterface
    {
        if ($this->start + $i > $this->end) {
            $count = $this->count();
            throw new IndexOutOfBoundsException("Cannot update index $i. Length of vector is $count");
        }

        if ($this->start + $i === $this->end) {
            return $this->append($value);
        }

        return new SubVector($this->hasher, $this->equalizer, $this->meta, $this->vector->update($this->start + $i, $value), $this->start, $this->end);
    }

    /**
     * @return T
     */
    public function get(int $i)
    {
        if ($i >= 0 && $i < $this->count()) {
            return $this->vector->get($i + $this->start);
        }

        throw new IndexOutOfBoundsException("Cannot access value at index $i.");
    }

    public function pop(): PersistentVectorInterface
    {
        if ($this->end - 1 <= $this->start) {
            return PersistentVector::empty($this->hasher, $this->equalizer);
        }

        return new SubVector($this->hasher, $this->equalizer, $this->meta, $this->vector, $this->start, $this->end - 1);
    }

    public function sliceNormalized(int $start, int $end): PersistentVectorInterface
    {
        return new SubVector($this->hasher, $this->equalizer, $this->meta, $this->vector, $this->start + $start, $this->start + $end);
    }

    public function asTransient(): void
    {
        throw new MethodNotSupportedException('asTransient is not supported on SubVector');
    }
}
