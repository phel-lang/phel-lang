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
        return $value !== null && $value !== false;
    }
}
