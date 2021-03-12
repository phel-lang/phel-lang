<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LinkedList;

use EmptyIterator;
use IteratorAggregate;
use Phel\Lang\EqualizerInterface;
use Phel\Lang\EqualsInterface;
use Phel\Lang\HashableInterface;
use Phel\Lang\HasherInterface;
use RuntimeException;
use Traversable;

/**
 * @template T
 * @template-implements PersistentListInterface<T>
 */
class EmptyList implements PersistentListInterface, IteratorAggregate, HashableInterface, EqualsInterface
{
    private EqualizerInterface $equalizer;
    private HasherInterface $hasher;

    public function __construct(HasherInterface $hasher, EqualizerInterface $equalizer)
    {
        $this->hasher = $hasher;
        $this->equalizer = $equalizer;
    }

    public function prepend($value): PersistentListInterface
    {
        return new PersistentList($this->hasher, $this->equalizer, $value, $this, 1);
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
