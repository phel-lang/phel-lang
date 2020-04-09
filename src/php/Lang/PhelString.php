<?php

namespace Phel\Lang;

class PhelString extends Phel {

    /**
     * @var string
     */
    protected $value;

    public function __construct(string $value) {
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }

    public function hash() {
        return $this->getValue();
    }

    public function equals($other): bool {
        return $other instanceof PhelString && $this->value == $other->getValue();
    }

    public function isTruthy(): bool {
        return strlen($this->value) > 0;
    }

    public function __toString() {
        return '"' . addslashes($this->value) . '"';
    }
}