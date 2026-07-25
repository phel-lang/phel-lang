<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * Default `SourceLocationInterface::copyLocationFrom()` for types that already
 * implement the start/end accessors. Values with an unlocated `$other` keep
 * their own span.
 */
trait CopyLocationFromTrait
{
    abstract public function setStartLocation(?SourceLocation $startLocation): static;

    abstract public function setEndLocation(?SourceLocation $endLocation): static;

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
