<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Printer;

final class Symbol extends AbstractType implements IIdentical
{
    private static int $symGenCounter = 1;

    private string $name;

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
}
