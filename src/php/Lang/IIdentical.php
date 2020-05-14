<?php

namespace Phel\Lang;

interface IIdentical {

    /**
     * Checks if $other is identical to $this.
     * 
     * @param mixed $other The value to compare.
     * 
     * @return bool
     */
    public function identical($other): bool;
}