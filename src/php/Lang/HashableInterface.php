<?php

declare(strict_types=1);

namespace Phel\Lang;

interface HashableInterface
{
    /**
     * @return int Return the hash of this object
     */
    public function hash(): int;
}
