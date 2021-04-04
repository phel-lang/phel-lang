<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * @template TSelf of PushInterface
 */
interface PushInterface
{
    /**
     * Pushes a new value of the data structure.
     *
     * @param mixed $x The new value
     *
     * @return TSelf
     */
    public function push($x);
}
