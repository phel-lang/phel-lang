<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LinkedList;

use Exception;
use InvalidArgumentException;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\SeqInterface;

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
     * @param T                          $first
     * @param PersistentListInterface<T> $rest
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly ?PersistentMapInterface $meta,
        private readonly mixed $first,
        private $rest,
        private readonly int $count,
    ) {}

    /**
     * @return T
     */
    public function __invoke(?int $index)
    {
        if ($index === null) {
            throw new InvalidArgumentException('List cannot be indexed with nil');
        }

        return $this->get($index);
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
        if ($values === []) {
            return self::empty($hasher, $equalizer);
        }

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

    public function withMeta(?PersistentMapInterface $meta): static
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
        if ($i < 0 || $i >= $this->count) {
            throw new IndexOutOfBoundsException('Index out of bounds');
        }

        $list = $this;
        for ($j = 0; $j < $i; ++$j) {
            $list = $list->rest();
        }

        /** @var T $result */
        $result = $list->first();
        return $result;
    }

    public function equals(mixed $other): bool
    {
        if ($this === $other) {
            return true;
        }

        // Sequential collections compare equal when they yield the same elements
        // in order, regardless of concrete type (list vs vector vs lazy seq).
        // Maps and sets do not implement SeqInterface, so they are excluded.
        if (!$other instanceof SeqInterface || !$other instanceof Traversable) {
            return false;
        }

        $node = $this;
        $visited = 0;
        foreach ($other as $rightValue) {
            if ($visited >= $this->count) {
                return false;
            }

            if (!$this->equalizer->equals($node->first(), $rightValue)) {
                return false;
            }

            $node = $node->cdr();
            ++$visited;
        }

        return $visited === $this->count;
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
        for ($s = $this; $s !== null; $s = $s->cdr()) {
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
