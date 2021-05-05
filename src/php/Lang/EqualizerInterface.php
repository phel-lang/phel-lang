<?php

declare(strict_types=1);

namespace Phel\Lang;

interface EqualizerInterface
{
    /**
     * @param mixed $a Left value
     * @param mixed $b Right value
     *
     * @return bool True, if $a is equals $b
     */
    public function equals($a, $b): bool;
}
