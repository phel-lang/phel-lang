<?php

declare(strict_types=1);

namespace Phel\Lang;

interface PushInterface
{
    /**
     * Pushes a new value of the data structure.
     *
     * @param mixed $x The new value
     */
    public function push($x): PushInterface;
}
