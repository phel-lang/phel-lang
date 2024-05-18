<?php

declare(strict_types=1);

namespace Phel\Lang;

interface HasherInterface
{
    /**
     * @return int The hash of the given value
     */
    public function hash(mixed $value): int;
}
