<?php

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use Phel\Printer;

class Set extends AbstractType implements Countable, Iterator, ICons, IPush, IConcat
{

    /**
     * @var mixed[]
     */
    protected $data = [];

    /**
     * Constructor
     *
     * @param mixed[] $data A list of all values
     */
    public function __construct(array $data)
    {
        $this->data = [];
        $this->concat($data);
    }

    public function isTruthy(): bool
    {
        return true;
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

    public function next()
    {
        next($this->data);
    }

    public function key()
    {
        return key($this->data);
    }

    public function valid()
    {
        return key($this->data) !== null;
    }

    public function rewind()
    {
        reset($this->data);
    }

    public function cons($x): ICons
    {
        $this->push($x);
        return $this;
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

    public function union(Set $set): Set
    {
        $this->concat($set->toPhpArray());
        return $this;
    }

    public function intersection(Set $set): Set
    {
        $this->data = array_intersect_key($this->data, $set->toPhpArray());
        return $this;
    }

    public function difference(Set $set): Set
    {
        $difference = array_diff_key($this->data, $set->toPhpArray());
        $this->data = [];
        $this->concat($difference);
        return $this;
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
     * @param mixed $offset The access key of the Set.
     *
     * @return string
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
