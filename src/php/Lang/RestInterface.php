<?php

declare(strict_types=1);

namespace Phel\Lang;

use NoDiscard;

/**
 * @template T of RestInterface
 */
interface RestInterface
{
    /**
     * Return the sequence without the first element. If the sequence is empty returns an empty sequence.
     *
     * @return T
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function rest();
}
