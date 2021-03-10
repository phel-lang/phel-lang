<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;

class VectorA implements PersistentVectorInterface
{
    private array $data;
    private array $tail;
    private int $count;

    public function __construct(int $count, array $data, array $tail)
    {
        $this->count = $count;
        $this->data = $data;
        $this->tail = $tail;
    }

    public function append($value): PersistentVectorInterface
    {
        if (count($this->tail) < self::BRANCH_FACTOR) {
            return new VectorA($this->count + 1, $this->data, [...$this->tail, $value]);
        }

        return new VectorA($this->count + 1, [...$this->data, $this->tail], [$value]);
    }

    public function count(): int
    {
        return $this->count;
    }

    public function update(int $i, $value): PersistentVectorInterface
    {
        return $this;
    }

    /**
     * @return mixed
     */
    public function get(int $i)
    {
        return null;
    }

    public function pop(): PersistentVectorInterface
    {
        return $this;
    }
}
