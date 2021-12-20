<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LinkedList;

use EmptyIterator;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\Exceptions\IndexOutOfBoundsException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;
use Traversable;

/**
 * @template T
 * @implements PersistentListInterface<T>
 * @extends AbstractType<PersistentList<T>>
 */
class EmptyList extends AbstractType implements PersistentListInterface
{
    private EqualizerInterface $equalizer;
    private HasherInterface $hasher;
    private ?PersistentMapInterface $meta;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentMapInterface $meta)
    {
        $this->hasher = $hasher;
        $this->equalizer = $equalizer;
        $this->meta = $meta;
    }

    public function getMeta(): ?PersistentMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentMapInterface $meta)
    {
        return new EmptyList($this->hasher, $this->equalizer, $meta);
    }

    /**
     * @param T $value
     *
     * @return PersistentListInterface<T>
     */
    public function prepend($value): PersistentListInterface
    {
        return new PersistentList($this->hasher, $this->equalizer, $this->meta, $value, $this, 1);
    }

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
     *
     * @return T
     */
    public function get(int $i)
    {
        throw new IndexOutOfBoundsException('Index out of bounds');
    }

    public function equals($other): bool
    {
        return $other instanceof EmptyList;
    }

    public function hash(): int
    {
        return 1;
    }

    public function getIterator(): Traversable
    {
        return new EmptyIterator();
    }

    public function first()
    {
        return null;
    }

    /**
     * @return EmptyList
     */
    public function rest()
    {
        return $this;
    }

    public function cdr()
    {
        return null;
    }

    public function toArray(): array
    {
        return [];
    }

    /**
     * Concatenates a value to the data structure.
     *
     * @param mixed[] $xs The value to concatenate
     *
     * @return PersistentListInterface
     */
    public function concat($xs)
    {
        return PersistentList::fromArray($this->hasher, $this->equalizer, $xs);
    }

    /**
     * @param mixed $x
     *
     * @return PersistentListInterface
     */
    public function cons($x)
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
     * @return mixed|null
     */
    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        throw new \Exception('offsetSet not supported on lists');
    }

    public function offsetUnset($offset): void
    {
        throw new \Exception('offsetUnset not supported on lists');
    }

    public function contains($key): bool
    {
        return false;
    }
}
