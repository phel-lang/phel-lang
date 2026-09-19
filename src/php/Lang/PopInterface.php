<?php

declare(strict_types=1);

namespace Phel\Lang;

use NoDiscard;

/**
 * @template TSelf of PopInterface
 */
interface PopInterface
{
    /**
     * Removes a value from the data structure.
     *
     * @return TSelf
     */
    #[NoDiscard('the result is a new collection, the receiver is unchanged')]
    public function pop();
}
