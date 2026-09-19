<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use NoDiscard;
use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;

use Traversable;

use function sprintf;

/**
 * @template T
 *
 * @extends AbstractPersistentVector<T>
 */
final class SubVector extends AbstractPersistentVector
{
    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     * @param PersistentVectorInterface<T>              $vector
     */
    public function __construct(
        HasherInterface $hasher,
        EqualizerInterface $equalizer,
        ?PersistentMapInterface $meta,
        private readonly PersistentVectorInterface $vector,
        private readonly int $start,
        private readonly int $end,
    ) {
        parent::__construct($hasher, $equalizer, $meta);
    }

    public function count(): int
    {
        return max(0, $this->end - $this->start);
    }

    /**
     * @return self<T>|null
     */
    #[NoDiscard('the result is a new collection, the receiver is unchanged')]
    public function cdr(): ?self
    {
        if ($this->start + 1 < $this->end) {
            return new self($this->hasher, $this->equalizer, $this->meta, $this->vector, $this->start + 1, $this->end);
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        for ($i = $this->start; $i < $this->end; ++$i) {
            $result[] = $this->vector->get($i);
        }

        return $result;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    #[NoDiscard('the result is a new collection, the receiver is unchanged')]
    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->hasher, $this->equalizer, $meta, $this->vector, $this->start, $this->end);
    }

    /**
     * @return Traversable<T>
     */
    public function getIterator(): Traversable
    {
        for ($i = $this->start; $i < $this->end; ++$i) {
            yield $this->vector->get($i);
        }
    }

    /**
     * @param T $value
     *
     * @return PersistentVectorInterface<T>
     */
    #[NoDiscard('the result is a new collection, the receiver is unchanged')]
    public function append($value): PersistentVectorInterface
    {
        return new self($this->hasher, $this->equalizer, $this->meta, $this->vector->update($this->end, $value), $this->start, $this->end + 1);
    }

    /**
     * @param T $value
     *
     * @return PersistentVectorInterface<T>
     */
    #[NoDiscard('the result is a new collection, the receiver is unchanged')]
    public function update(int $i, $value): PersistentVectorInterface
    {
        if ($this->start + $i > $this->end) {
            $count = $this->count();
            throw new IndexOutOfBoundsException(sprintf('Cannot update index %d. Length of vector is %d', $i, $count));
        }

        if ($this->start + $i === $this->end) {
            return $this->append($value);
        }

        return new self($this->hasher, $this->equalizer, $this->meta, $this->vector->update($this->start + $i, $value), $this->start, $this->end);
    }

    /**
     * @return T
     */
    public function get(int $i)
    {
        if ($i >= 0 && $i < $this->count()) {
            return $this->vector->get($i + $this->start);
        }

        throw new IndexOutOfBoundsException(sprintf('Cannot access value at index %d.', $i));
    }

    /**
     * @return PersistentVectorInterface<T>
     */
    #[NoDiscard('the result is a new collection, the receiver is unchanged')]
    public function pop(): PersistentVectorInterface
    {
        if ($this->end - 1 <= $this->start) {
            /** @var PersistentVector<T> $empty */
            $empty = PersistentVector::empty($this->hasher, $this->equalizer);
            return $empty;
        }

        return new self($this->hasher, $this->equalizer, $this->meta, $this->vector, $this->start, $this->end - 1);
    }

    public function asTransient(): never
    {
        throw new MethodNotSupportedException('asTransient is not supported on SubVector');
    }

    /**
     * @return PersistentVectorInterface<T>
     */
    #[NoDiscard('the result is a new collection, the receiver is unchanged')]
    public function cons(mixed $x): PersistentVectorInterface
    {
        /** @var PersistentVectorInterface<T> $result */
        $result = PersistentVector::fromArray($this->hasher, $this->equalizer, [$x, ...$this->toArray()]);
        return $result;
    }

    /**
     * @return PersistentVectorInterface<T>
     */
    protected function sliceNormalized(int $start, int $end): PersistentVectorInterface
    {
        return new self($this->hasher, $this->equalizer, $this->meta, $this->vector, $this->start + $start, $this->start + $end);
    }
}
