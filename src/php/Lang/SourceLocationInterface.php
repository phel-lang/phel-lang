<?php

declare(strict_types=1);

namespace Phel\Lang;

interface SourceLocationInterface
{
    /**
     * @return static
     */
    public function setStartLocation(?SourceLocation $startLocation);

    /**
     * @return static
     */
    public function setEndLocation(?SourceLocation $endLocation);

    public function getStartLocation(): ?SourceLocation;

    public function getEndLocation(): ?SourceLocation;

    /**
     * @param mixed $other
     *
     * @return static
     */
    public function copyLocationFrom($other);
}
