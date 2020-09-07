<?php

declare(strict_types=1);

namespace Phel\Lang;

abstract class AbstractType implements IMeta, ISourceLocation
{
    use TSourceLocation;
    use TMeta;

    /**
     * Computes a hash of the object
     */
    abstract public function hash(): string;

    /**
     * Check if $other is equals to $this.
     *
     * @param mixed $other The other value
     */
    abstract public function equals($other): bool;
}
