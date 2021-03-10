<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Vector;

use Exception;
use Iterator;

class RangeIterator implements Iterator
{
    private PersistentVector $vector;
    private int $start;
    private int $end;
    private int $currentIndex;
    private int $base;
    private ?array $currentArray = null;

    public function __construct(PersistentVector $vector, int $start, int $end)
    {
        $this->vector = $vector;
        $this->start = $start;
        $this->end = $end;
        $this->currentIndex = $start;
        $this->base = $this->currentIndex - ($this->currentIndex % 32);

        if ($this->start < count($this->vector)) {
            $this->currentArray = $this->vector->getArrayForIndex($this->currentIndex);
        }
    }

    public function current()
    {
        assert(isset($this->currentArray[$this->currentIndex & 0x01f]));
        return $this->currentArray[$this->currentIndex & 0x01f];
    }

    public function next(): void
    {
        if ($this->currentIndex < $this->end) {
            $this->currentIndex++;
            if ($this->currentIndex - $this->base === 32) {
                $this->currentArray = $this->vector->getArrayForIndex($this->currentIndex);
                $this->base += 32;
            }
        } else {
            throw new Exception('No such element');
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

    public function key()
    {
        return $this->currentIndex;
    }
}
