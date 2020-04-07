<?php

namespace Phel\Lang;

class Keyword implements Phel {

    /**
     * @var string
     */
    protected $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function hash() {
        return ':' . $this->getName();
    }

    public function equals($other): bool {
        return $other instanceof Keyword && $this->name == $other->getName();
    }

    public function isTruthy(): bool {
        return true;
    }
}