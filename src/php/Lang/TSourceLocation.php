<?php

declare(strict_types=1);

namespace Phel\Lang;

trait TSourceLocation
{
    private ?SourceLocation $startLocation = null;
    private ?SourceLocation $endLocation = null;

    public function setStartLocation(?SourceLocation $startLocation): void
    {
        $this->startLocation = $startLocation;
    }

    public function setEndLocation(?SourceLocation $endLocation): void
    {
        $this->endLocation = $endLocation;
    }

    public function getStartLocation(): ?SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): ?SourceLocation
    {
        return $this->endLocation;
    }

    public function copyLocationFrom($other)
    {
        if ($other && $other instanceof ISourceLocation) {
            $this->setStartLocation($other->getStartLocation());
            $this->setEndLocation($other->getEndLocation());
        }

        return $this;
    }
}
