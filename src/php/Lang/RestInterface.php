<?php

declare(strict_types=1);

namespace Phel\Lang;

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
    public function rest();
}
