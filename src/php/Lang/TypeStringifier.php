<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Shared\Printer\Printer;
use Throwable;

use function get_debug_type;
use function is_scalar;
use function mb_strlen;
use function mb_substr;

final class TypeStringifier
{
    /** Cap for the truncated rendering produced by {@see self::describe()}. */
    private const int MAX_DESCRIBE_LENGTH = 60;

    public static function toString(TypeInterface $value): string
    {
        try {
            return Printer::readable()->print($value);
        } catch (Throwable) {
            return '#object[' . get_debug_type($value) . ']';
        }
    }

    /**
     * Bounded rendering of an arbitrary value, for error messages.
     *
     * Only values whose printed form is inherently small get printed:
     * scalars, `nil`, keywords and symbols, truncated at
     * {@see self::MAX_DESCRIBE_LENGTH}. Anything else, in particular a
     * collection or a lazy seq that may be deep or infinite, degrades to its
     * type name, so building an error message can never exhaust memory or
     * hang forcing realization.
     */
    public static function describe(mixed $value): string
    {
        if (!self::isBoundedToPrint($value)) {
            return '#object[' . get_debug_type($value) . ']';
        }

        try {
            $printed = Printer::readable()->print($value);
        } catch (Throwable) {
            return '#object[' . get_debug_type($value) . ']';
        }

        if (mb_strlen($printed) <= self::MAX_DESCRIBE_LENGTH) {
            return $printed;
        }

        return mb_substr($printed, 0, self::MAX_DESCRIBE_LENGTH) . '...';
    }

    private static function isBoundedToPrint(mixed $value): bool
    {
        return $value === null
            || is_scalar($value)
            || $value instanceof Keyword
            || $value instanceof Symbol;
    }
}
