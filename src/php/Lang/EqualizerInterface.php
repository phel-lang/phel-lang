<?php

declare(strict_types=1);

namespace Phel\Lang;

interface EqualizerInterface
{
    /**
     * @return bool True, if $a is equals $b
     */
    public function equals(mixed $a, mixed $b): bool;
}
