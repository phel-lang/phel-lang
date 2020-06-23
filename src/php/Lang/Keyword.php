<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Printer;

final class Keyword extends AbstractType implements IIdentical
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function hash(): string
    {
        return ':' . $this->getName();
    }

    public function equals($other): bool
    {
        return $other instanceof self && $this->name == $other->getName();
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
