<?php

namespace Phel\Lang;

use Phel\Stream\SourceLocation;

trait TSourceLocation {

    /**
     * @var ?SourceLocation
     */
    private $startLocation;

    /**
     * @var ?SourceLocation
     */
    private $endLocation;

    public function setStartLocation(?SourceLocation $startLocation): void {
        $this->startLocation = $startLocation;
    }

    public function setEndLocation(?SourceLocation $endLocation): void {
        $this->endLocation = $endLocation;
    }

    public function getStartLocation(): ?SourceLocation {
        return $this->startLocation;
    }

    public function getEndLocation(): ?SourceLocation {
        return $this->endLocation;
    }
}