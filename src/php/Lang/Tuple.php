<?php

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use Iterator;

class Tuple implements Phel, ArrayAccess, Countable, Iterator, ISlice, ICons, ICdr, IRest {

    use SourceLocationTrait;

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
        return isset($this->data[$offset]) ? $this->data[$offset] : Nil::getInstance();
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
            return Nil::getInstance();
        }
    }

    public function rest(): IRest {
        return new Tuple(array_slice($this->data, 1), $this->isUsingBracket());
    }
}