<?php

namespace Phel\Lang;

trait ComparableTrait
{
    public function equals($other): bool
    {
        return $this == $other;
    }
}
