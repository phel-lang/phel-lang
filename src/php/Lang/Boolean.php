<?php

namespace Phel\Lang;

class Boolean extends Phel {

    /**
     * @var bool
     */
    protected $value;

    public function __construct(bool $value) {
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }

    public function hash() {
        return $this->getValue();
    }

    public function equals($other): bool {
        return $other instanceof Boolean && $this->value == $other->getValue();
    }

    public function isTruthy(): bool {
        return $this->value;
    }

    public function __toString()
    {
        return $this->value ? 'true' : 'false';
    }
}