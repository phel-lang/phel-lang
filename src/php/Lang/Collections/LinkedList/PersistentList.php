<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LinkedList;

use Exception;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Traversable;

use function count;

/**
 * @template T
 *
 * @implements PersistentListInterface<T>
 *
 * @extends AbstractType<PersistentListInterface<T>>
 */
final class PersistentList extends AbstractType implements PersistentListInterface
{
    private int $hashCache = 0;

    /**
     * @param T $first
     * @param PersistentListInterface<T> $rest
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly ?PersistentMapInterface $meta,
        private readonly mixed $first,
        private $rest,
        private readonly int $count,
    ) {
    }

    /**
     * @return EmptyList<T>
     */
    public static function empty(HasherInterface $hasher, EqualizerInterface $equalizer): PersistentListInterface
    {
        return new EmptyList($hasher, $equalizer, null);
    }

    public static function fromArray(HasherInterface $hasher, EqualizerInterface $equalizer, array $values): PersistentListInterface
    {
        $result = self::empty($hasher, $equalizer);
        for ($i = count($values) - 1; $i >= 0; --$i) {
            $result = $result->prepend($values[$i]);
        }

        return $result;
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentMapInterface $meta): self
    {
        return new self($this->hasher, $this->equalizer, $meta, $this->first, $this->rest, $this->count);
    }

    /**
     * @param T $value
     *
     * @return PersistentListInterface<T>
     */
    public function prepend($value): PersistentListInterface
    {
        return new self($this->hasher, $this->equalizer, $this->meta, $value, $this, $this->count + 1);
    }

    /**
     * @return PersistentListInterface<T>
     */
    public function pop(): PersistentListInterface
    {
        return $this->rest;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * @throws IndexOutOfBoundsException
     *
     * @return T
     */
    public function get(int $i)
    {
        $list = $this;
        for ($j = 0; $j < $this->count; ++$j) {
            if ($j === $i) {
                /** @var T $result */
                $result = $list->first();
                return $result;
            }

            $list = $list->rest();
        }

        throw new IndexOutOfBoundsException('Index out of bounds');
    }

    public function equals(mixed $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->count !== $other->count()) {
            return false;
        }

        $s = $this;
        $ms = $other;
        for ($s = $this; $s != null; $s = $s->cdr(), $ms = $ms->cdr()) {
            /** @var PersistentList $s */
            /** @var ?PersistentList $ms */
            if (!$ms instanceof self || !$this->equalizer->equals($s->first(), $ms->first())) {
                return false;
            }
        }

        return true;
    }

    public function hash(): int
    {
        if ($this->hashCache === 0) {
            $this->hashCache = 1;
            foreach ($this as $value) {
                $this->hashCache = 31 * $this->hashCache + $this->hasher->hash($value);
            }
        }

        return $this->hashCache;
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

    public function first()
    {
        return $this->first;
    }

    /**
     * @return PersistentListInterface
     */
    public function rest()
    {
        return $this->rest;
    }

    /**
     * @return PersistentListInterface|null
     */
    public function cdr()
    {
        if ($this->count === 1) {
            return null;
        }

        return $this->rest;
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    /**
     * Concatenates a value to the data structure.
     *
     * @param array<int, mixed> $xs The value to concatenate
     */
    public function concat($xs): PersistentListInterface
    {
        return self::fromArray($this->hasher, $this->equalizer, [...$this->toArray(), ...$xs]);
    }

    public function cons(mixed $x): PersistentListInterface
    {
        return $this->prepend($x);
    }

    /**
     * @param int $offset
     */
    public function offsetExists($offset): bool
    {
        return $offset >= 0 && $offset < $this->count;
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

    public function offsetSet($offset, $value): void
    {
        throw new Exception('offsetSet not supported on lists');
    }

    public function offsetUnset($offset): void
    {
        throw new Exception('offsetUnset not supported on lists');
    }

    public function contains($key): bool
    {
        return $this->offsetExists($key);
    }
}
