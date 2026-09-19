<?php

declare(strict_types=1);

namespace Phel\Lang;

use NoDiscard;

/**
 * @template T of ConsInterface
 */
interface ConsInterface
{
    /**
     * Appends a value to the front of a data structure.
     *
     * @return T
     */
    #[NoDiscard('the result is a new collection, the receiver is unchanged')]
    public function cons(mixed $x);
}
