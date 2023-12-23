<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Printer\Printer;
use Stringable;

/**
 * @template T
 *
 * @implements TypeInterface<T>
 */
abstract class AbstractType implements TypeInterface, Stringable
{
    private ?SourceLocation $startLocation = null;

    private ?SourceLocation $endLocation = null;

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }

    public function setStartLocation(?SourceLocation $startLocation): static
    {
        $this->startLocation = $startLocation;
        return $this;
    }

    public function setEndLocation(?SourceLocation $endLocation): static
    {
        $this->endLocation = $endLocation;
        return $this;
    }

    public function getStartLocation(): ?SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): ?SourceLocation
    {
        return $this->endLocation;
    }

    /**
     * Copies the start and end location from $other.
     *
     * @param mixed $other The object to copy from
     */
    public function copyLocationFrom(mixed $other): static
    {
        if ($other instanceof SourceLocationInterface) {
            return $this
                ->setStartLocation($other->getStartLocation())
                ->setEndLocation($other->getEndLocation());
        }

        return $this;
    }
}
