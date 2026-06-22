<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Queue;

use Countable;
use IteratorAggregate;
use Phel\Lang\AbstractType;
use Phel\Lang\CdrInterface;
use Phel\Lang\Collections\LinkedList\PersistentList;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\ConsInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\FirstInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\PopInterface;
use Phel\Lang\PushInterface;
use Phel\Lang\TypeInterface;
use Traversable;
use UnderflowException;

use function array_reverse;

/**
 * Persistent first-in / first-out queue.
 *
 * Two-stack (banker's queue) representation: `front` holds elements in
 * dequeue order, `rear` holds elements pushed since the last reversal in
 * reverse order. Pushing appends to `rear` (O(1)); popping removes the
 * head of `front`. When `front` becomes empty, the queue swaps `rear`
 * (reversed) into `front`, giving amortised O(1) `push` / `peek` / `pop`.
 *
 * @extends AbstractType<PersistentQueue>
 *
 * @implements IteratorAggregate<int, mixed>
 * @implements FirstInterface<mixed>
 * @implements CdrInterface<PersistentQueue>
 * @implements ConsInterface<PersistentQueue>
 * @implements PushInterface<PersistentQueue>
 * @implements PopInterface<PersistentQueue>
 */
final class PersistentQueue extends AbstractType implements TypeInterface, Countable, IteratorAggregate, FirstInterface, CdrInterface, ConsInterface, PushInterface, PopInterface
{
    private ?int $hashCache = null;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     * @param PersistentListInterface<mixed>            $front
     * @param PersistentListInterface<mixed>            $rear
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly ?PersistentMapInterface $meta,
        private readonly PersistentListInterface $front,
        private readonly PersistentListInterface $rear,
        private readonly int $count,
    ) {}

    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): self
    {
        $emptyList = PersistentList::empty($hasher, $equalizer);
        return new self($hasher, $equalizer, null, $emptyList, $emptyList, 0);
    }

    /**
     * @param array<int, mixed> $values
     */
    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $values): self
    {
        $queue = self::empty($hasher, $equalizer);
        foreach ($values as $value) {
            $queue = $queue->push($value);
        }

        return $queue;
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>|null
     */
    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function withMeta(?PersistentMapInterface $meta): static
    {
        return new self($this->hasher, $this->equalizer, $meta, $this->front, $this->rear, $this->count);
    }

    /**
     * Pushes `$x` onto the back of the queue.
     */
    public function push(mixed $x): self
    {
        if ($this->count === 0) {
            return new self(
                $this->hasher,
                $this->equalizer,
                $this->meta,
                $this->front->cons($x),
                $this->rear,
                1,
            );
        }

        return new self(
            $this->hasher,
            $this->equalizer,
            $this->meta,
            $this->front,
            $this->rear->cons($x),
            $this->count + 1,
        );
    }

    /**
     * Alias for `push`. Lets `(conj queue x)` append at the back, matching
     * Clojure's queue semantics.
     */
    public function cons(mixed $x): self
    {
        return $this->push($x);
    }

    /**
     * Removes the front element. Throws `UnderflowException` when the
     * queue is empty.
     */
    public function pop(): self
    {
        if ($this->count === 0) {
            throw new UnderflowException('Cannot pop empty queue');
        }

        $newFront = $this->front->pop();
        $newCount = $this->count - 1;

        if ($newCount === 0) {
            return self::empty($this->hasher, $this->equalizer);
        }

        if ($newFront->count() === 0) {
            return new self(
                $this->hasher,
                $this->equalizer,
                $this->meta,
                $this->reverseRear(),
                PersistentList::empty($this->hasher, $this->equalizer),
                $newCount,
            );
        }

        return new self(
            $this->hasher,
            $this->equalizer,
            $this->meta,
            $newFront,
            $this->rear,
            $newCount,
        );
    }

    public function first(): mixed
    {
        if ($this->count === 0) {
            return null;
        }

        return $this->front->first();
    }

    public function cdr(): ?self
    {
        return $this->count <= 1 ? null : $this->pop();
    }

    public function count(): int
    {
        return max(0, $this->count);
    }

    /**
     * @return Traversable<int, mixed>
     */
    public function getIterator(): Traversable
    {
        $i = 0;
        foreach ($this->front as $value) {
            yield $i++ => $value;
        }

        foreach (array_reverse($this->rear->toArray()) as $value) {
            yield $i++ => $value;
        }
    }

    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }

        if (!$other instanceof self || $other->count !== $this->count) {
            return false;
        }

        $right = iterator_to_array($other, false);
        foreach ($this as $idx => $value) {
            if (!$this->equalizer->equals($value, $right[$idx])) {
                return false;
            }
        }

        return true;
    }

    public function hash(): int
    {
        if ($this->hashCache !== null) {
            return $this->hashCache;
        }

        return $this->hashCache = $this->hasher->orderedHash($this);
    }

    /**
     * @return PersistentListInterface<mixed>
     */
    private function reverseRear(): PersistentListInterface
    {
        $reversed = PersistentList::empty($this->hasher, $this->equalizer);
        foreach ($this->rear as $value) {
            $reversed = $reversed->cons($value);
        }

        return $reversed;
    }
}
