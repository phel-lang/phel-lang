<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Printer\Printer;

final class Keyword extends AbstractType implements IdenticalInterface, FnInterface
{
    use MetaTrait;

    private string $name;
    private int $hash;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->hash = crc32(':' . $name);
    }

    public static function create(string $name): Keyword
    {
        return new Keyword($name);
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $default
     */
    public function __invoke(Table $obj, $default = null)
    {
        return $obj[$this] ?? $default;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function hash(): int
    {
        return $this->hash;
    }

    public function equals($other): bool
    {
        return $other instanceof self && $this->name === $other->getName();
    }

    public function identical($other): bool
    {
        return $other instanceof self && $this->name == $other->getName();
    }

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }
}
