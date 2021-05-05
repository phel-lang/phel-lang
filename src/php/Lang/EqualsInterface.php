<?php

declare(strict_types=1);

namespace Phel\Lang;

interface EqualsInterface
{
    /**
     * @param mixed $other
     *
     * @return bool True, if this is equals $other
     */
    public function equals($other): bool;
}
