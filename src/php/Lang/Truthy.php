<?php

declare(strict_types=1);

namespace Phel\Lang;

final class Truthy
{
    /**
     * Check if the given value evaluates to true.
     *
     * @param mixed $value
     */
    public static function isTruthy($value): bool
    {
        return $value !== null && $value !== false;
    }
}
