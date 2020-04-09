<?php

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use Iterator;

class PhelArray extends Phel implements ArrayAccess, Countable, Iterator, ICons, ISlice, ICdr, IRest {

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
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
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
        return new PhelArray([$x, ...$this->data]);
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

    public function __toString() {
        return '@[' . implode(" ", array_map(fn($x) => $x->__toString(), $this->data)) . ']';
    }
}