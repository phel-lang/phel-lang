<?php

namespace Phel\Lang;

use Phel\Stream\SourceLocation;

abstract class Phel {

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

    /**
     * Check if the value is thruthy
     * 
     * @return bool
     */
    public abstract function isTruthy(): bool;

    /**
     * Computes a hash of the object
     * 
     * @return string
     */
    public abstract function hash(): string;

    /**
     * Check if $other is equals to $this.
     * 
     * @param mixed $other The other value.
     * 
     * @return bool
     */
    public abstract function equals($other): bool;
}