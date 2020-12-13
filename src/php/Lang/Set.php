<?php

declare(strict_types=1);

namespace Phel\Lang;

use Countable;
use Iterator;
use Phel\Printer;

class Set extends AbstractType implements Countable, Iterator, ISeq, ICons, IPush, IConcat
{
    /** @var mixed[] */
    protected array $data = [];

    /**
     * @param mixed[] $data
     */
    public function __construct(array $data)
    {
        $this->data = [];
        $this->concat($data);
    }

    public function hash(): string
    {
        return spl_object_hash($this);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function current()
    {
        return current($this->data);
    }

    public function next(): void
    {
        next($this->data);
    }

    public function key()
    {
        return key($this->data);
    }

    public function valid(): bool
    {
        return key($this->data) !== null;
    }

    public function rewind(): void
    {
        reset($this->data);
    }

    public function cons($x): ICons
    {
        $this->push($x);
        return $this;
    }

    public function first()
    {
        if (empty($this->data)) {
            return null;
        }

        $this->rewind();
        return $this->current();
    }

    public function cdr(): ?ICdr
    {
        if ($this->count() <= 1) {
            return null;
        }

        return new PhelArray(array_values(array_slice($this->data, 1)));
    }

    public function rest(): IRest
    {
        $this->rewind();
        $this->next();

        return new PhelArray(array_values(array_slice($this->data, 1)));
    }

    public function push($x): IPush
    {
        $hash = $this->offsetHash($x);
        $this->data[$hash] = $x; // Don't need to check if $x is already there, just override.

        return $this;
    }

    public function concat($xs): IConcat
    {
        foreach ($xs as $x) {
            $this->push($x);
        }

        return $this;
    }

    public function intersection(Set $set): Set
    {
        return new Set(array_intersect_key($this->data, $set->toPhpArray()));
    }

    public function difference(Set $set): Set
    {
        return new Set(array_diff_key($this->data, $set->toPhpArray()));
    }

    public function equals($other): bool
    {
        return $this->toPhpArray() == $other->toPhpArray();
    }

    public function toPhpArray(): array
    {
        return $this->data;
    }

    /**
     * Creates a hash for the given key.
     *
     * @param mixed $offset The access key of the Set
     */
    private function offsetHash($offset): string
    {
        if ($offset instanceof AbstractType) {
            return $offset->hash();
        }

        if (is_object($offset)) {
            return spl_object_hash($offset);
        }

        return (string) $offset;
    }

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }
}
