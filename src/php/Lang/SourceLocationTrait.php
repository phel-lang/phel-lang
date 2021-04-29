<?php

declare(strict_types=1);

namespace Phel\Lang;

trait SourceLocationTrait
{
    private ?SourceLocation $startLocation = null;
    private ?SourceLocation $endLocation = null;

    public function setStartLocation(?SourceLocation $startLocation)
    {
        $this->startLocation = $startLocation;
        return $this;
    }

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
            $this->setStartLocation($other->getStartLocation());
            $this->setEndLocation($other->getEndLocation());
        }

        return $this;
    }
}
