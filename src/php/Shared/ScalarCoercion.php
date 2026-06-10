<?php

declare(strict_types=1);

namespace Phel\Shared;

use function array_map;
use function array_values;
use function is_array;
use function is_numeric;
use function is_scalar;

/**
 * Coerces loosely-typed configuration values (read as `mixed` from the
 * project config / JSON) into a concrete scalar, falling back to a default
 * when the value is not coercible. Centralizes the `(string) $mixed`-style
 * casts so they stay type-safe under strict static analysis.
 */
final class ScalarCoercion
{
    public static function toString(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function toInt(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function toFloat(mixed $value, float $default = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * Coerce a config value into a list of strings, falling back to the
     * default when it is not an array. Each element is coerced via toString().
     *
     * @param list<string> $default
     *
     * @return list<string>
     */
    public static function toStringList(mixed $value, array $default = []): array
    {
        if (!is_array($value)) {
            return $default;
        }

        return array_values(array_map(
            static fn(mixed $element): string => self::toString($element),
            $value,
        ));
    }
}
