<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Map;

use Iterator;
use RuntimeException;

/**
 * @template K
 * @template V
 *
 * @implements Iterator<K, V>
 */
class ArrayNodeIterator implements Iterator
{
    /** @var array<int, HashMapNodeInterface<K, V>> A fixed size array of nodes */
    private array $childNodes;
    private int $index = 0;
    private ?Iterator $nestedIterator = null;

    public function __construct(array $childNodes)
    {
        $this->childNodes = array_values($childNodes);
    }

    /**
     * @return V
     */
    public function current(): mixed
    {
        if ($this->nestedIterator) {
            return $this->nestedIterator->current();
        }

        throw new RuntimeException('Nested iterator is not initialized');
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

    private function nextIndex(): void
    {
        $this->index++;

        if ($this->index < count($this->childNodes)) {
            $this->initializeNestedIterator($this->index);
        } else {
            $this->nestedIterator = null;
        }
    }

    public function valid(): bool
    {
        if ($this->nestedIterator) {
            return $this->nestedIterator->valid();
        }

        return $this->index < count($this->childNodes);
    }

    public function rewind(): void
    {
        $this->index = 0;
        if (count($this->childNodes) > 0) {
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

        throw new RuntimeException('Nested iterator is not initialized');
    }

    private function initializeNestedIterator(int $index): void
    {
        /** @var Iterator $nestedIterator */
        $nestedIterator = $this->childNodes[$index]->getIterator();
        $nestedIterator->rewind();

        $this->nestedIterator = $nestedIterator;
    }
}
