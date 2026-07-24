<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Iterator;
use RuntimeException;

use function array_filter;
use function array_values;
use function count;

/**
 * @template TKey
 * @template TValue
 *
 * @implements Iterator<TKey, TValue>
 */
final class ArrayNodeIterator implements Iterator
{
    /** @var list<HashMapNodeInterface<TKey, TValue>> A list of nodes */
    private readonly array $childNodes;

    private readonly int $count;

    private int $index = 0;

    /** @var Iterator<TKey, TValue>|null */
    private ?Iterator $nestedIterator = null;

    /**
     * @param array<int, ?HashMapNodeInterface<TKey, TValue>> $childNodes
     */
    public function __construct(array $childNodes)
    {
        // array_filter drops the null slots and array_values re-indexes
        // into a gap-free list, so the caller may pass a sparse array.
        $this->childNodes = array_values(array_filter($childNodes));
        $this->count = count($this->childNodes);
    }

    /**
     * @return TValue
     */
    public function current(): mixed
    {
        if ($this->nestedIterator instanceof Iterator) {
            return $this->nestedIterator->current();
        }

        throw new RuntimeException('Nested iterator is not initialized');
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
        if ($this->childNodes !== []) {
            $this->initializeNestedIterator($this->index);
        }
    }

    /**
     * @return TKey
     */
    public function key(): mixed
    {
        if ($this->nestedIterator instanceof Iterator) {
            return $this->nestedIterator->key();
        }

        throw new RuntimeException('Nested iterator is not initialized');
    }

    private function nextIndex(): void
    {
        ++$this->index;

        if ($this->index < $this->count) {
            $this->initializeNestedIterator($this->index);
        } else {
            $this->nestedIterator = null;
        }
    }

    private function initializeNestedIterator(int $index): void
    {
        /** @var Iterator $nestedIterator */
        $nestedIterator = $this->childNodes[$index]->getIterator();
        $nestedIterator->rewind();

        $this->nestedIterator = $nestedIterator;
    }
}
