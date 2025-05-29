<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use Iterator;

use function assert;

final class RangeIterator implements Iterator
{
    private int $currentIndex;

    private readonly int $vectorCount;

    private ?array $currentArray = null;

    public function __construct(
        private readonly PersistentVector $vector,
        private readonly int $start,
        private readonly int $end,
    ) {
        $this->currentIndex = $start;
        $this->vectorCount = $this->vector->count();

        if ($this->start < $this->vectorCount) {
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
        if ($this->currentIndex < $this->end && ($this->currentIndex & 0x1f) === 0) {
            $this->currentArray = $this->vector->getArrayForIndex($this->currentIndex);
        }
    }

    public function valid(): bool
    {
        return $this->currentIndex < $this->end;
    }

    public function rewind(): void
    {
        $this->currentIndex = $this->start;

        $this->currentArray = null;
        if ($this->start < $this->vectorCount) {
            $this->currentArray = $this->vector->getArrayForIndex($this->currentIndex);
        }
    }

    public function key(): mixed
    {
        return $this->currentIndex;
    }
}
