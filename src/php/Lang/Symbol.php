<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Printer;

final class Symbol extends AbstractType implements IIdentical
{
    use HashableTrait;

    private static int $symGenCounter = 1;

    private ?string $namespace;

    private string $name;

    public function __construct(?string $namespace, string $name)
    {
        $this->namespace = $namespace;
        $this->name = $name;
    }

    public static function create($name)
    {
        $pos = strpos($name, '/');

        if ($pos === false || $name === '/') {
            return new Symbol(null, $name);
        }

        return new Symbol(substr($name, 0, $pos), substr($name, $pos + 1));
    }

    public static function createForNamespace($namespace, $name)
    {
        return new Symbol($namespace, $name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getFullName(): string
    {
        if ($this->namespace) {
            return $this->namespace . '/' . $this->name;
        }

        return $this->name;
    }

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }

    public static function gen(string $prefix = '__phel_'): Symbol
    {
        return Symbol::create($prefix . (self::$symGenCounter++));
    }

    public static function resetGen(): void
    {
        self::$symGenCounter = 1;
    }

    public function equals($other): bool
    {
        return $other instanceof Symbol
            && $this->name === $other->getName()
            && $this->namespace === $other->getNamespace();
    }

    public function identical($other): bool
    {
        return $this->equals($other);
    }
}
