<?php

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use Phel\Printer;

class Tuple extends Phel implements ArrayAccess, Countable, Iterator, ISlice, ICons, ICdr, IRest, IPush, IConcat {

    /**
     * @var Phel[]
     */
    protected $data;

    /**
     * @var bool
     */
    protected $usingBracket;

    public function __construct($data, $usingBracket = false)
    {
        $this->data = $data;
        $this->usingBracket = $usingBracket;
    }

    public static function create(...$values) {
        return new Tuple($values);
    }

    public static function createBracket(...$values) {
        return new Tuple($values, true);
    }

    public function offsetSet($offset, $value) {
        throw new \InvalidArgumentException('Calling offsetSet is not supported on Tuples since they are immutable');
    }

    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset) {
        throw new \InvalidArgumentException('Calling offsetUnset is not supported on Tuples since they are immutable');
    }

    public function offsetGet($offset) {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function count() {
        return count($this->data);
    }

    public function isUsingBracket() {
        return $this->usingBracket;
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

    public function update($offset, $value) {
        if ($offset < 0 || $offset > count($this->data)) {
            throw new InvalidArgumentException('Index out of bounds: ' . $offset . ' [0,' . count($this->data) . ']');
        }
        if (is_null($offset)) {
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

        if ($this->getStartLocation()) {
            $res->setStartLocation($this->getStartLocation());
        }

        if ($this->getEndLocation()) {
            $res->getEndLocation($this->getEndLocation());
        }

        return $res;
    }

    public function slice($offset = 0, $length = null): ISlice {
        return new Tuple(
            array_slice($this->data, $offset, $length), 
            $this->isUsingBracket()
        );
    }

    public function cons($x): ICons {
        return new Tuple([$x, ...$this->data], $this->isUsingBracket());
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

    public function cdr() {
        if (count($this->data) - 1 > 0) {
            return new Tuple(array_slice($this->data, 1), $this->isUsingBracket());
        } else {
            return null;
        }
    }

    public function rest(): IRest {
        return new Tuple(array_slice($this->data, 1), $this->isUsingBracket());
    }

    public function push($x): IPush {
        return new Tuple([...$this->data, $x]);
    }

    public function concat($xs): IConcat {
        $newData = $this->data;
        foreach ($xs as $x) {
            $newData[] = $x;
        }

        return new Tuple($newData, $this->isUsingBracket());
    }

    public function __toString() {
        $printer = new Printer();
        return $printer->print($this, true);
    }
}