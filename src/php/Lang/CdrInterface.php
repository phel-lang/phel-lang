<?php

declare(strict_types=1);

namespace Phel\Lang;

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
    public function cdr();
}
