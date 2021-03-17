<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LinkedList;

use EmptyIterator;
use IteratorAggregate;
use Phel\Lang\AbstractType;
use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;
use Traversable;

/**
 * @template T
 * @template-implements PersistentListInterface<T>
 * @extends AbstractType<EmptyList<T>>
 */
class EmptyList extends AbstractType implements PersistentListInterface, IteratorAggregate
{
    private EqualizerInterface $equalizer;
    private HasherInterface $hasher;
    private ?PersistentHashMapInterface $meta;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer, ?PersistentHashMapInterface $meta)
    {
        $this->hasher = $hasher;
        $this->equalizer = $equalizer;
        $this->meta = $meta;
    }

    public function getMeta(): ?PersistentHashMapInterface
    {
        return $this->meta;
    }

    public function withMeta(?PersistentHashMapInterface $meta)
    {
        return new EmptyList($this->hasher, $this->equalizer, $meta);
    }

    public function prepend($value): PersistentListInterface
    {
        return new PersistentList($this->hasher, $this->equalizer, $this->meta, $value, $this, 1);
    }

    public function pop(): PersistentListInterface
    {
        throw new RuntimeException('Can not pop empty list');
    }

    public function count(): int
    {
        return 0;
    }

    /**
     * @return T
     */
    public function get(int $i)
    {
        throw new \RuntimeException('Index out of bounds');
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
     * @return static<T>
     */
    public function rest()
    {
        return $this;
    }

    public function cdr()
    {
        return null;
    }
}
