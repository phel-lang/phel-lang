<?php

namespace Phel\Lang;

abstract class Phel implements IMeta, ISourceLocation
{
    use TSourceLocation;
    use TMeta;

    /**
     * Check if the value is thruthy
     *
     * @return bool
     */
    abstract public function isTruthy(): bool;

    /**
     * Computes a hash of the object
     *
     * @return string
     */
    abstract public function hash(): string;

    /**
     * Check if $other is equals to $this.
     *
     * @param mixed $other The other value.
     *
     * @return bool
     */
    abstract public function equals($other): bool;
}
