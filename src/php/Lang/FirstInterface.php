<?php

declare(strict_types=1);

namespace Phel\Lang;

/**
 * @template T
 */
interface FirstInterface
{
    /**
     * Returns the first value.
     *
     * @return T|null
     */
    public function first();
}
