<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Iterator;

use function count;

/**
 * @template TKey
 * @template TValue
 *
 * @implements Iterator<TKey, TValue>
 */
final class IndexedNodeIterator implements Iterator
{
    /** @var array<int, array{0: TKey|null, 1: HashMapNodeInterface<TKey, TValue>|TValue}> */
    private readonly array $entries;

    private readonly int $count;

    private int $index = 0;

    /** @var Iterator<TKey, TValue>|null */
    private ?Iterator $nestedIterator = null;

    /**
     * @param array<int, array{0: mixed, 1: mixed}> $entries
     */
    public function __construct(array $entries)
    {
        $this->entries = array_values($entries);
        $this->count = count($this->entries);
    }

    /**
     * @return TValue
     */
    public function current(): mixed
    {
        if ($this->nestedIterator instanceof Iterator) {
            return $this->nestedIterator->current();
        }

        /** @var TValue $result */
        $result = $this->entries[$this->index][1];
        return $result;
    }

    public function next(): void
    {
        if ($this->nestedIterator instanceof Iterator && $this->nestedIterator->valid()) {
            $this->nestedIterator->next();

            if (!$this->nestedIterator->valid()) {
                $this->nextIndex();
            }
        } else {
            $this->nextIndex();
        }
    }

    public function valid(): bool
    {
        if ($this->nestedIterator instanceof Iterator) {
            return $this->nestedIterator->valid();
        }

        return $this->index < $this->count;
    }

    public function rewind(): void
    {
        $this->index = 0;
        if ($this->entries === []) {
            return;
        }

        if ($this->entries[$this->index][0] !== null) {
            return;
        }

        $this->initializeNestedIterator($this->index);
    }

    /**
     * @return TKey
     */
    public function key(): mixed
    {
        if ($this->nestedIterator instanceof Iterator) {
            return $this->nestedIterator->key();
        }

        return $this->entries[$this->index][0];
    }

    private function nextIndex(): void
    {
        ++$this->index;
        if ($this->index < $this->count && $this->entries[$this->index][0] === null) {
            $this->initializeNestedIterator($this->index);
        } else {
            $this->nestedIterator = null;
        }
    }

    private function initializeNestedIterator(int $index): void
    {
        $child = $this->entries[$index][1];
        if (!$child instanceof HashMapNodeInterface) {
            $this->nestedIterator = null;
            return;
        }

        /** @var Iterator<TKey, TValue> $nestedIterator */
        $nestedIterator = $child->getIterator();
        $nestedIterator->rewind();
        $this->nestedIterator = $nestedIterator;
    }
}
