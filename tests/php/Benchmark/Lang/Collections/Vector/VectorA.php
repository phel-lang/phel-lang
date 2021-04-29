<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang\Collections\Vector;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\SourceLocation;

final class VectorA implements PersistentVectorInterface
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

    public function cdr(): void
    {
        // TODO: Implement cdr() method.
    }

    public function concat($xs): void
    {
        // TODO: Implement concat() method.
    }

    public function getIterator(): void
    {
        // TODO: Implement getIterator() method.
    }

    public function offsetExists($offset): void
    {
        // TODO: Implement offsetExists() method.
    }

    public function offsetGet($offset): void
    {
        // TODO: Implement offsetGet() method.
    }

    public function offsetSet($offset, $value): void
    {
        // TODO: Implement offsetSet() method.
    }

    public function offsetUnset($offset): void
    {
        // TODO: Implement offsetUnset() method.
    }

    public function equals($other): bool
    {
        // TODO: Implement equals() method.
        return true;
    }

    public function first(): void
    {
        // TODO: Implement first() method.
    }

    public function hash(): int
    {
        // TODO: Implement hash() method.
        return 0;
    }

    public function getMeta(): ?PersistentMapInterface
    {
        // TODO: Implement getMeta() method.
        return null;
    }

    public function withMeta(?PersistentMapInterface $meta): void
    {
        // TODO: Implement withMeta() method.
    }

    public function push($x): void
    {
        // TODO: Implement push() method.
    }

    public function rest(): void
    {
        // TODO: Implement rest() method.
    }

    public function toArray(): array
    {
        // TODO: Implement toArray() method.
        return [];
    }

    public function slice(int $offset = 0, ?int $length = null): void
    {
        // TODO: Implement slice() method.
    }

    public function setStartLocation(?SourceLocation $startLocation): void
    {
        // TODO: Implement setStartLocation() method.
    }

    public function setEndLocation(?SourceLocation $endLocation): void
    {
        // TODO: Implement setEndLocation() method.
    }

    public function getStartLocation(): ?SourceLocation
    {
        // TODO: Implement getStartLocation() method.
        return null;
    }

    public function getEndLocation(): ?SourceLocation
    {
        // TODO: Implement getEndLocation() method.
        return null;
    }

    public function copyLocationFrom($other): void
    {
        // TODO: Implement copyLocationFrom() method.
    }
}
