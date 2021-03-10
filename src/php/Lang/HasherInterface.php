<?php

declare(strict_types=1);

namespace Phel\Lang;

interface HasherInterface
{
    /**
     * @param mixed $value The value to hash
     *
     * @return int The hash of the given value
     */
    public function hash($value): int;
}
