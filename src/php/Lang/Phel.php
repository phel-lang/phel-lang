<?php

namespace Phel\Lang;

abstract class Phel implements IMeta, ISourceLocation {

    use TSourceLocation, TMeta;

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