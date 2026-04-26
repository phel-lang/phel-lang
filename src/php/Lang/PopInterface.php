<?php

declare(strict_types=1);

namespace Phel\Lang;

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
    public function pop();
}
