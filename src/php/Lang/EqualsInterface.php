<?php

declare(strict_types=1);

namespace Phel\Lang;

interface EqualsInterface
{
    /**
     * @return bool True, if this is equals $other
     */
    public function equals(mixed $other): bool;
}
