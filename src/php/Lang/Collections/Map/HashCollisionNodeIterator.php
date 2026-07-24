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
final class HashCollisionNodeIterator implements Iterator
{
    private int $index = 0;

    private readonly int $entriesCount;

    /**
     * @param array{TKey,TValue,TKey,TValue} $entries
     */
    public function __construct(
        private readonly array $entries,
    ) {
        $this->entriesCount = count($this->entries);
    }

    /**
     * @return TValue
     */
    public function current(): mixed
    {
        /** @var TValue $result */
        $result = $this->entries[$this->index + 1];
        return $result;
    }

    public function next(): void
    {
        $this->index += 2;
    }

    public function valid(): bool
    {
        return $this->index < $this->entriesCount;
    }

    public function rewind(): void
    {
        $this->index = 0;
    }

    /**
     * @return TKey
     */
    public function key(): mixed
    {
        /** @var TKey $result */
        $result = $this->entries[$this->index];
        return $result;
    }
}
