<?php

namespace Phel\Lang;

class Truthy
{

    /**
     * Check if a value is truthy.
     *
     * @param mixed $value The provided value
     *
     * @return bool
     */
    public static function isTruthy($value): bool
    {
        if ($value instanceof Phel) {
            return $value->isTruthy();
        } else {
            return $value != null && $value !== false;
        }
    }
}
