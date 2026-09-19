<?php

declare(strict_types=1);

namespace Phel\Lang;

use NoDiscard;

/**
 * @template TSelf of PushInterface
 */
interface PushInterface
{
    /**
     * Pushes a new value of the data structure.
     *
     * @return TSelf
     */
    #[NoDiscard('the result is a new collection, the receiver is unchanged')]
    public function push(mixed $x);
}
