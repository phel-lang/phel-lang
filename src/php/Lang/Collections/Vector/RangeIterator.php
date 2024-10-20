<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use Iterator;

use function assert;
use function count;

final class RangeIterator implements Iterator
{
    private int $currentIndex;

    private int $base;

    private ?array $currentArray = null;

    public function __construct(
        private readonly PersistentVector $vector,
        private readonly int $start,
        private readonly int $end,
    ) {
        $this->currentIndex = $start;
        $this->base = $this->currentIndex - ($this->currentIndex % 32);

        if ($this->start < count($this->vector)) {
            $this->currentArray = $this->vector->getArrayForIndex($this->currentIndex);
        }
    }

    public function current(): mixed
    {
        assert($this->currentArray !== null);
        return $this->currentArray[$this->currentIndex & 0x01f];
    }

    public function next(): void
    {
        ++$this->currentIndex;
        if ($this->currentIndex < $this->end && $this->currentIndex - $this->base === 32) {
            $this->currentArray = $this->vector->getArrayForIndex($this->currentIndex);
            $this->base += 32;
        }
    }

    public function valid(): bool
    {
        return $this->currentIndex < $this->end;
    }

    public function rewind(): void
    {
        $this->currentIndex = $this->start;
    }

    public function key(): mixed
    {
        return $this->currentIndex;
    }
}
