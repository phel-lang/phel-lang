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
class HashCollisionNodeIterator implements Iterator
{
    /** @var array<K|V> */
    private array $entries;
    private int $index = 0;

    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    /**
     * @return V
     */
    public function current(): mixed
    {
        /** @var V $result */
        $result = $this->entries[$this->index + 1];
        return $result;
    }

    public function next(): void
    {
        $this->index += 2;
    }

    public function valid(): bool
    {
        return $this->index < count($this->entries);
    }

    public function rewind(): void
    {
        $this->index = 0;
    }

    /**
     * @return K
     */
    public function key(): mixed
    {
        /** @var K $result */
        $result = $this->entries[$this->index];
        return $result;
    }
}
