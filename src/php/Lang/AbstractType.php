<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Printer\Printer;

/**
 * @template TSelf
 *
 * @implements TypeInterface<TSelf>
 */
abstract class AbstractType implements TypeInterface
{
    private ?SourceLocation $startLocation = null;
    private ?SourceLocation $endLocation = null;

    /**
     * @return static
     */
    public function setStartLocation(?SourceLocation $startLocation)
    {
        $this->startLocation = $startLocation;
        return $this;
    }

    /**
     * @return static
     */
    public function setEndLocation(?SourceLocation $endLocation)
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
     *
     * @return static
     */
    public function copyLocationFrom($other): self
    {
        if ($other && $other instanceof SourceLocationInterface) {
            return $this
                ->setStartLocation($other->getStartLocation())
                ->setEndLocation($other->getEndLocation());
        }

        return $this;
    }

    public function __toString(): string
    {
        return Printer::readable()->print($this);
    }
}
