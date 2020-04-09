<?php

namespace Phel\Lang;

class Symbol extends Phel {

    private static $symGenCounter = 1;

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

    public function __toString()
    {
        return $this->name;
    }

    public static function gen($prefix = '__phel_') {
        return new Symbol($prefix . (self::$symGenCounter++));
    }

    public static function resetGen() {
        self::$symGenCounter = 1;
    }

    public function hash() {
        return $this->getName();
    }

    public function equals($other): bool {
        return $other instanceof Symbol && $this->name == $other->getName();
    }

    public function isTruthy(): bool {
        return true;
    }
}