<?php

declare(strict_types=1);

namespace Phel\Lang;

use NoDiscard;

/**
 * @template T of CdrInterface
 */
interface CdrInterface
{
    /**
     * Return the sequence without the first element. If the sequence is empty returns null.
     *
     * @return T|null
     */
    #[NoDiscard('the result is a new instance, the receiver is unchanged')]
    public function cdr();
}
