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
     *
     * @return TSelf
     */
    public function push(mixed $x);
}
