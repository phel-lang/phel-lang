<?php

declare(strict_types=1);

namespace Phel\Lang;

use InvalidArgumentException;

use function array_key_exists;
use function preg_match_all;
use function sprintf;
use function str_contains;

use const PREG_SET_ORDER;

/**
 * Prepares the arguments of `phel.core/format` and `printf` for PHP's
 * `sprintf`, which casts an object to 1 with a warning when a numeric
 * directive consumes it. Each `Ratio`, `BigInt` and `BigDecimal` is converted
 * according to the directive that consumes it; a `%s` keeps its Phel
 * representation through `__toString`.
 */
final readonly class FormatArguments
{
    /**
     * Mirrors the directive grammar of `sprintf`: `%`, an optional `n$`
     * position, flags (including `'c` padding), a width and a precision that
     * are either digits or `*` (optionally `*n$`), then the conversion.
     */
    private const string DIRECTIVE = '/%(?:(\d+)\$)?(?:[-+ 0]|\'.)*(\*(?:(\d+)\$)?|\d+)?(?:\.(\*(?:(\d+)\$)?|\d*))?(.)/s';

    private const string FLOAT_CONVERSIONS = 'eEfFgGhH';

    private const string INTEGER_CONVERSIONS = 'bcdouxX';

    /**
     * @param list<mixed> $args
     *
     * @return list<mixed>
     */
    public static function coerce(string $format, array $args): array
    {
        preg_match_all(self::DIRECTIVE, $format, $directives, PREG_SET_ORDER);

        $next = 0;
        foreach ($directives as $directive) {
            $conversion = $directive[6];
            if ($conversion === '%') {
                continue;
            }

            if ($directive[2] === '*' && $directive[3] === '') {
                ++$next;
            }

            if ($directive[4] === '*' && $directive[5] === '') {
                ++$next;
            }

            $index = $directive[1] !== '' ? (int) $directive[1] - 1 : $next++;
            if (array_key_exists($index, $args)) {
                $args[$index] = self::convert($args[$index], $conversion);
            }
        }

        return $args;
    }

    private static function convert(mixed $value, string $conversion): mixed
    {
        if (!$value instanceof Ratio && !$value instanceof BigInt && !$value instanceof BigDecimal) {
            return $value;
        }

        if (str_contains(self::FLOAT_CONVERSIONS, $conversion)) {
            return NumericCoercion::toFloat($value);
        }

        if (!str_contains(self::INTEGER_CONVERSIONS, $conversion)) {
            return $value;
        }

        if (!$value instanceof BigInt) {
            throw new InvalidArgumentException(sprintf(
                'format: %%%s expects an integer, got %s %s',
                $conversion,
                PhelType::nameOf($value),
                $value,
            ));
        }

        if (!$value->fitsInPhpInt()) {
            throw new InvalidArgumentException(sprintf(
                'format: %%%s expects an integer that fits in a PHP int, got %s',
                $conversion,
                $value,
            ));
        }

        return $value->toInt();
    }
}
