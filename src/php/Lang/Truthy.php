<?php

declare(strict_types=1);

namespace Phel\Lang;

final class Truthy
{
    /**
     * Check if the given value evalutaes to true
     *
     * @param mixed $value The value
     */
    public static function isTruthy($value): bool
    {
        return $value !== null && $value !== false;
    }
}
