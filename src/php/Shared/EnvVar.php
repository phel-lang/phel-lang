<?php

declare(strict_types=1);

namespace Phel\Shared;

use InvalidArgumentException;

use function ctype_digit;
use function getenv;
use function in_array;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Reads Phel's own environment switches with one set of rules: case and
 * surrounding whitespace do not matter, an unset or empty variable is not set,
 * and a value that fits no rule throws naming the variable, so a typo stops
 * the command instead of being read as something else.
 *
 * @internal
 */
final class EnvVar
{
    public const string WARN_DEPRECATIONS = 'PHEL_WARN_DEPRECATIONS';

    public const string TEST_WORKERS = 'PHEL_TEST_WORKERS';

    private const array TRUE_SPELLINGS = ['1', 'true', 'yes', 'on'];

    private const array FALSE_SPELLINGS = ['0', 'false', 'no', 'off'];

    /**
     * @throws InvalidArgumentException when the value is not a boolean spelling
     */
    public static function flag(string $name): ?bool
    {
        $value = self::read($name);
        if ($value === null) {
            return null;
        }

        $spelling = strtolower($value);
        if (in_array($spelling, self::TRUE_SPELLINGS, true)) {
            return true;
        }

        if (in_array($spelling, self::FALSE_SPELLINGS, true)) {
            return false;
        }

        throw new InvalidArgumentException(sprintf(
            '%s must be one of 1, true, yes, on, 0, false, no or off, got "%s".',
            $name,
            $value,
        ));
    }

    /**
     * @throws InvalidArgumentException when the value is not a whole number of at least `$min`
     */
    public static function integer(string $name, int $min): ?int
    {
        $value = self::read($name);
        if ($value === null) {
            return null;
        }

        if (!ctype_digit($value) || (int) $value < $min) {
            throw new InvalidArgumentException(sprintf(
                '%s must be a whole number of at least %d, got "%s".',
                $name,
                $min,
                $value,
            ));
        }

        return (int) $value;
    }

    /**
     * For a variable other tools set with their own values, such as `CI=woodpecker`:
     * on unless unset, empty or a false spelling, and never an error.
     */
    public static function isOn(string $name): bool
    {
        $value = self::read($name);

        return $value !== null && !in_array(strtolower($value), self::FALSE_SPELLINGS, true);
    }

    private static function read(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
