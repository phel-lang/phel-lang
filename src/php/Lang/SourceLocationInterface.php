<?php

declare(strict_types=1);

namespace Phel\Lang;

interface SourceLocationInterface
{
    public function setStartLocation(?SourceLocation $startLocation): static;

    public function setEndLocation(?SourceLocation $endLocation): static;

    public function getStartLocation(): ?SourceLocation;

    public function getEndLocation(): ?SourceLocation;

    public function copyLocationFrom(mixed $other): static;
}
