<?php

namespace Phel\Lang;

use ArrayAccess;
use Countable;
use Iterator;

class Nil implements Phel, ArrayAccess, Countable, Iterator, ICons, ISlice, ICdr, IRest {

    private static $instance = null;

    private function __construct() {}

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new Nil();
        }

        return self::$instance;
    }

    public function offsetSet($offset, $value) {
        throw new \Exception('not supported');
    }

    public function offsetExists($offset) {
        return false;
    }

    public function offsetUnset($offset) {
        return;
    }

    public function offsetGet($offset) {
        return $this;
    }

    public function count() {
        return 0;
    }

    public function current() {
        return $this;
    }

    public function key() {
        return $this;
    }

    public function next() {
        return false;
    }

    public function rewind() {
    }

    public function valid() {
        return false;
    }

    public function hash() {
        throw new \Exception('not hashable');
    }

    public function equals($other): bool {
        return $other === self::getInstance();
    }

    public function isTruthy(): bool {
        return false;
    }

    public function slice($offset = 0, $length = null): ISlice {
        return $this;
    }

    public function cons($x): ICons {
        return new PhelArray([$x]);
    }

    public function cdr() {
        return $this;
    }

    public function rest(): IRest {
        return $this;
    }
}