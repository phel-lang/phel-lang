<?php

namespace Phel\Lang;

use Phel\Printer;

class Symbol extends Phel implements IIdentical
{

    /**
     * @var int
     */
    private static $symGenCounter = 1;

    /**
     * @var string
     */
    protected $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }

    public static function gen(string $prefix = '__phel_'): Symbol
    {
        return new Symbol($prefix . (self::$symGenCounter++));
    }

    public static function resetGen(): void
    {
        self::$symGenCounter = 1;
    }

    public function hash(): string
    {
        return $this->getName();
    }

    public function equals($other): bool
    {
        return $other instanceof Symbol && $this->name == $other->getName();
    }

    public function identical($other): bool
    {
        return $other instanceof Symbol && $this->name == $other->getName();
    }

    public function isTruthy(): bool
    {
        return true;
    }
}
