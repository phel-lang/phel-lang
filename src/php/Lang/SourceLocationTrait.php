<?php

namespace Phel\Lang;

use Phel\Stream\SourceLocation;

trait SourceLocationTrait {

    /**
     * @var SourceLocation
     */
    private $startLocation;

    /**
     * @var SourceLocation
     */
    private $endLocation;

    public function setStartLocation(SourceLocation $startLocation) {
        $this->startLocation = $startLocation;
    }

    public function setEndLocation(SourceLocation $endLocation) {
        $this->endLocation = $endLocation;
    }

    public function getStartLocation() {
        return $this->startLocation;
    }

    public function getEndLocation() {
        return $this->endLocation;
    }
}