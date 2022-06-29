<?php

declare(strict_types=1);

namespace Phel\Lang;

final class Truthy
{
    /**
     * Check if the given value evaluates to true.
     */
    public static function isTruthy(mixed $value): bool
    {
        return $value !== null && $value !== false;
    }
}
