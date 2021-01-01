<?php

declare(strict_types=1);

namespace Phel\Lang;

interface ConsInterface
{
    /**
     * Appends a value to the front of a data structure.
     *
     * @param mixed $x The value to cons
     */
    public function cons($x): ConsInterface;
}
