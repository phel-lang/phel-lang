<?php

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use Phel\Printer;

class PhelArray extends Phel implements ArrayAccess, Countable, Iterator, ICons, ISlice, ICdr, IRest, IPop, IRemove, IPush, IConcat {

    /**
     * @var Phel[]
     */
    protected $data = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function create(...$values) {
        return new PhelArray($values);
    }

    public function offsetSet($offset, $value) {
        if ($offset < 0 || $offset > count($this->data)) {
            throw new InvalidArgumentException('Index out of bounds: ' . $offset . ' [0,' . count($this->data) . ']');
        }
        if (is_null($offset)) {
            unset($this->data[$offset]);
            $this->data = array_values($this->data); // reindex
        } else {
            if ($offset == count($this->data)) {
                $this->data[] = $value;
            } else {
                $this->data[$offset] = $value;
            }
        }
    }

    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset) {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function count(): int {
        return count($this->data);
    }

    public function current() {
        return current($this->data);
    }

    public function key() {
        return key($this->data);
    }

    public function next() {
        return next($this->data);
    }

    public function rewind() {
        reset($this->data);
    }

    public function valid() {
        return key($this->data) !== null;
    }

    public function hash() {
        return spl_object_hash($this);
    }

    public function equals($other): bool {
        return $this === $other;
    }

    public function isTruthy(): bool {
        return true;
    }

    public function slice($offset = 0, $length = null): ISlice {
        return new PhelArray(array_slice($this->data, $offset, $length));
    }

    public function cons($x): ICons {
        array_unshift($this->data, $x);
        return $this;
    }

    public function toPhpArray() {
        return $this->data;
    }

    public function cdr() {
        if (count($this->data) - 1 > 0) {
            return new PhelArray(array_slice($this->data, 1));
        } else {
            return null;
        }
    }

    public function rest(): IRest {
        return new PhelArray(array_slice($this->data, 1));
    }

    public function pop() {
        return array_pop($this->data);
    }

    public function remove($offset, $length = null): IRemove {
        $length = $length ?? 1;

        return new PhelArray(array_splice($this->data, $offset, $length));
    }

    public function push($x): IPush {
        $this->data[] = $x;
        return $this;
    }

    public function concat($xs): IConcat {
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