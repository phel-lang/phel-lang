<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * @template T of ConsInterface
 */
interface ConsInterface
{
    /**
     * Appends a value to the front of a data structure.
     *
     * @param mixed $x The value to cons
     *
     * @return T
     */
    public function cons($x);
}
