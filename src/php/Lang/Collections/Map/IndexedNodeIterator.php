<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Iterator;

/**
 * @template K
 * @template V
 *
 * @implements Iterator<K, V>
 */
class IndexedNodeIterator implements Iterator
{
    /** @var array<int, array{0: K|null, 1: V|HashMapNodeInterface<K, V>}> */
    private array $entries;
    private int $index = 0;
    private ?Iterator $nestedIterator = null;

    public function __construct(array $entries)
    {
        $this->entries = array_values($entries);
    }

    /**
     * @return V
     */
    public function current(): mixed
    {
        if ($this->nestedIterator) {
            return $this->nestedIterator->current();
        }

        /** @var V $result */
        $result = $this->entries[$this->index][1];
        return $result;
    }

    public function next(): void
    {
        if ($this->nestedIterator && $this->nestedIterator->valid()) {
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
        if ($this->nestedIterator) {
            return $this->nestedIterator->valid();
        }

        return $this->index < count($this->entries);
    }

    public function rewind(): void
    {
        $this->index = 0;
        if (count($this->entries) > 0 && $this->entries[$this->index][0] === null) {
            $this->initializeNestedIterator($this->index);
        }
    }

    /**
     * @return K
     */
    public function key(): mixed
    {
        if ($this->nestedIterator) {
            return $this->nestedIterator->key();
        }

        return $this->entries[$this->index][0];
    }

    private function nextIndex(): void
    {
        $this->index++;
        if ($this->index < count($this->entries) && $this->entries[$this->index][0] === null) {
            $this->initializeNestedIterator($this->index);
        } else {
            $this->nestedIterator = null;
        }
    }

    private function initializeNestedIterator(int $index): void
    {
        $this->nestedIterator = $this->entries[$index][1]->getIterator();
        $this->nestedIterator->rewind();
    }
}
