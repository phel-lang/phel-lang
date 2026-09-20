<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LinkedList;

use EmptyIterator;
use Exception;
use NoDiscard;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use Phel\Lang\SeqInterface;
use RuntimeException;
use Traversable;

/**
 * @template T
 *
 * @implements PersistentListInterface<T>
 *
 * @extends AbstractType<PersistentListInterface<T>>
 */
final class EmptyList extends AbstractType implements PersistentListInterface
{
    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    public function __construct(
        private readonly HasherInterface $hasher,
        private readonly EqualizerInterface $equalizer,
        private readonly ?PersistentMapInterface $meta,
        private readonly bool $isList = true,
    ) {}

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
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function withMeta(?PersistentMapInterface $meta): static
    {
        /** @var self<T> $result */
        $result = new self($this->hasher, $this->equalizer, $meta, $this->isList);

        return $result;
    }

    public function isList(): bool
    {
        return $this->isList;
    }

    /**
     * @param T $value
     *
     * @return PersistentListInterface<T>
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function prepend($value): PersistentListInterface
    {
        return new PersistentList($this->hasher, $this->equalizer, $this->meta, $value, $this, 1, $this->isList);
    }

    /**
     * @return PersistentListInterface<T>
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function pop(): PersistentListInterface
    {
        throw new RuntimeException('Cannot pop empty list');
    }

    public function count(): int
    {
        return 0;
    }

    /**
     * @throws IndexOutOfBoundsException
     */
    public function get(int $i): never
    {
        throw new IndexOutOfBoundsException('Index out of bounds');
    }

    public function equals(mixed $other): bool
    {
        if ($other instanceof self) {
            return true;
        }

        // Any empty sequential collection (vector, lazy seq, …) compares equal
        // to the empty list. Maps and sets don't implement SeqInterface so
        // they're excluded even when empty.
        if (!$other instanceof SeqInterface || !$other instanceof Traversable) {
            return false;
        }

        foreach ($other as $ignored) {
            return false;
        }

        return true;
    }

    public function hash(): int
    {
        return 1;
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new EmptyIterator();
    }

    public function first(): null
    {
        return null;
    }

    /**
     * @return self<T>
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function rest(): self
    {
        return $this;
    }

    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function cdr(): null
    {
        return null;
    }

    /**
     * @return array<int, T>
     */
    public function toArray(): array
    {
        return [];
    }

    /**
     * Concatenates a value to the data structure.
     *
     * @param array<int, mixed> $xs The value to concatenate
     *
     * @return PersistentListInterface<T>
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function concat($xs): PersistentListInterface
    {
        /** @var PersistentListInterface<T> $result */
        $result = PersistentList::fromArray($this->hasher, $this->equalizer, $xs, $this->isList);

        return $result;
    }

    /**
     * @return PersistentListInterface<T>
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function cons(mixed $x): PersistentListInterface
    {
        return $this->prepend($x);
    }

    /**
     * @param int $offset
     */
    public function offsetExists($offset): bool
    {
        return false;
    }

    /**
     * @param int $offset
     *
     * @throws IndexOutOfBoundsException always: the empty list holds no offset
     *
     * @return never
     */
    public function offsetGet($offset): mixed
    {
        $this->get($offset);
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
        return false;
    }
}
