<?php

declare(strict_types=1);

namespace Phel\Lang;

interface PopInterface
{
    /**
     * Removes the value at the beginning of a sequence and return this removed value.
     */
    public function pop(): mixed;
}
