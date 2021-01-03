<?php

declare(strict_types=1);

namespace Phel\Lang;

interface SourceLocationInterface
{
    public function setStartLocation(?SourceLocation $startLocation): void;

    public function setEndLocation(?SourceLocation $endLocation): void;

    public function getStartLocation(): ?SourceLocation;

    public function getEndLocation(): ?SourceLocation;
}
