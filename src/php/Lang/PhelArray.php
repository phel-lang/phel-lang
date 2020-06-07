<?php

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use Phel\Printer;

class PhelArray extends Phel implements ArrayAccess, Countable, Iterator, ICons, ISlice, ICdr, IRest, IPop, IRemove, IPush, IConcat
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
        $this->data = $data;
    }

    /**
     * Create a new array.
     *
     * @param mixed[] $values A list of all values
     *
     * @return PhelArray
     */
    public static function create(...$values): PhelArray
    {
        return new PhelArray($values);
    }

    public function offsetSet($offset, $value)
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be bigger or equal zero. Given: ' . $offset);
        }

        if ($offset < count($this->data)) {
            $this->data[$offset] = $value;
        } else {
            for ($i = count($this->data); $i < $offset; $i++) {
                $this->data[$i] = null;
            }

            $this->data[$offset] = $value;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset)
    {
        if ($offset < 0 || $offset >= count($this->data)) {
            throw new InvalidArgumentException('Index out of bounds: ' . $offset . ' [0,' . count($this->data) . ')');
        }

        unset($this->data[$offset]);
        $this->data = array_values($this->data); // reindex
    }

    /**
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function count(): int
    {
        return count($this->data);
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

    public function slice(int $offset = 0, ?int $length = null): ISlice
    {
        return new PhelArray(array_slice($this->data, $offset, $length));
    }

    public function cons($x): ICons
    {
        array_unshift($this->data, $x);
        return $this;
    }

    public function toPhpArray(): array
    {
        return $this->data;
    }

    public function cdr(): ?ICdr
    {
        if (count($this->data) - 1 > 0) {
            return new PhelArray(array_slice($this->data, 1));
        } else {
            return null;
        }
    }

    public function rest(): IRest
    {
        return new PhelArray(array_slice($this->data, 1));
    }

    public function pop()
    {
        return array_pop($this->data);
    }

    public function remove(int $offset, ?int $length = null): IRemove
    {
        $length = $length ?? count($this);

        return new PhelArray(array_splice($this->data, $offset, $length));
    }

    public function push($x): IPush
    {
        $this->data[] = $x;
        return $this;
    }

    public function concat($xs): IConcat
    {
        foreach ($xs as $x) {
            $this->data[] = $x;
        }
        return $this;
    }

    public function __toString()
    {
        $printer = new Printer();
        return $printer->print($this, true);
    }
}
