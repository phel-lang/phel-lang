<?php

namespace Phel\Lang;

class Keyword extends Phel {

    use SourceLocationTrait;

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

    public function __toString()
    {
        return ':' . $this->name;
    }
}