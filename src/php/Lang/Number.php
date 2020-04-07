<?php

namespace Phel\Lang;

class Number implements Phel {

    /**
     * @var float|int
     */
    protected $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }

    public function hash() {
        return $this->value;
    }

    public function equals($other): bool {
        return $other instanceof Number && $this->value == $other->getValue();
    }

    public function isTruthy(): bool {
        return true;
    }
}