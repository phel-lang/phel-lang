<?php

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use Phel\Printer;

class Tuple extends Phel implements ArrayAccess, Countable, Iterator, ISlice, ICons, ICdr, IRest, IPush, IConcat
{

    /**
     * @var Phel[]
     */
    protected $data;

    /**
     * @var bool
     */
    protected $usingBracket;

    /**
     * Constructor
     *
     * @param array $data A list of values
     * @param bool $bool $usingBracket True if this is bracket tuple.
     */
    public function __construct(array $data, bool $usingBracket = false)
    {
        $this->data = $data;
        $this->usingBracket = $usingBracket;
    }

    /**
     * Create a new Tuple.
     *
     * @param Phel|scalar|null ...$values The values
     *
     * @return Tuple
     */
    public static function create(...$values): Tuple
    {
        return new Tuple($values);
    }

    /**
     * Create a new bracket Tuple.
     *
     * @param Phel|scalar|null ...$values The values
     *
     * @return Tuple
     */
    public static function createBracket(...$values): Tuple
    {
        return new Tuple($values, true);
    }

    public function offsetSet($offset, $value)
    {
        throw new \InvalidArgumentException('Calling offsetSet is not supported on Tuples since they are immutable');
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset)
    {
        throw new \InvalidArgumentException('Calling offsetUnset is not supported on Tuples since they are immutable');
    }

    /**
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function count()
    {
        return count($this->data);
    }

    public function isUsingBracket(): bool
    {
        return $this->usingBracket;
    }

    public function current()
    {
        return current($this->data);
    }

    public function key()
    {
        return key($this->data);
    }

    public function next()
    {
        next($this->data);
    }

    public function rewind()
    {
        reset($this->data);
    }

    public function valid()
    {
        return key($this->data) !== null;
    }

    /**
     * Update a tuple value. For internal use only.
     *
     * @param int $index The index to update
     * @param mixed $value The value to set on $index
     *
     * @return Tuple A copy of the tuple with an update value
     * @internal
     */
    public function update(int $offset, $value): Tuple
    {
        if ($offset < 0 || $offset > count($this->data)) {
            throw new InvalidArgumentException('Index out of bounds: ' . $offset . ' [0,' . count($this->data) . ']');
        }
        if (is_null($value)) {
            unset($this->data[$offset]);
            $res = new Tuple(array_values($this->data), $this->isUsingBracket()); // reindex
        } else {
            if ($offset == count($this->data)) {
                $res = new Tuple([...$this->data, $value], $this->isUsingBracket());
            } else {
                $newData = $this->data;
                $newData[$offset] = $value;
                $res = new Tuple($newData, $this->isUsingBracket());
            }
        }

        $start = $this->getStartLocation();
        if ($start) {
            $res->setStartLocation($start);
        }

        $end = $this->getEndLocation();
        if ($end) {
            $res->setEndLocation($end);
        }

        return $res;
    }

    public function slice(int $offset = 0, ?int $length = null): ISlice
    {
        return new Tuple(
            array_slice($this->data, $offset, $length),
            $this->isUsingBracket()
        );
    }

    public function cons($x): ICons
    {
        return new Tuple([$x, ...$this->data], $this->isUsingBracket());
    }

    public function hash(): string
    {
        return spl_object_hash($this);
    }

    public function equals($other): bool
    {
        return $this === $other;
    }

    public function isTruthy(): bool
    {
        return true;
    }

    public function cdr(): ?ICdr
    {
        if (count($this->data) - 1 > 0) {
            return new Tuple(array_slice($this->data, 1), $this->isUsingBracket());
        } else {
            return null;
        }
    }

    public function rest(): IRest
    {
        return new Tuple(array_slice($this->data, 1), $this->isUsingBracket());
    }

    public function push($x): IPush
    {
        return new Tuple([...$this->data, $x]);
    }

    public function concat($xs): IConcat
    {
        $newData = $this->data;
        foreach ($xs as $x) {
            $newData[] = $x;
        }

        return new Tuple($newData, $this->isUsingBracket());
    }

    /**
     * @internal
     */
    public function toArray()
    {
        return $this->data;
    }

    public function __toString()
    {
        $printer = new Printer();
        return $printer->print($this, true);
    }
}
