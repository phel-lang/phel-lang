<?php

declare(strict_types=1);

namespace Phel\Lang;

final class Truthy
{
    public static function isTruthy($value): bool
    {
        return $value !== null && $value !== false;
    }
}
