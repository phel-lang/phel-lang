<?php

declare(strict_types=1);

namespace Phel\Shared;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;

use function ctype_digit;
use function is_numeric;
use function sprintf;

/**
 * Reads a numeric command-line option. A value that is not a number, or is
 * below the minimum, throws naming the option, so the command can exit 2
 * instead of reading `abc` as 0.
 *
 * @internal
 */
final class NumericOption
{
    /**
     * @throws InvalidArgumentException when the value is not a whole number of at least `$min`
     *
     * @return ?int null when the option has no value and no default
     */
    public static function wholeNumber(InputInterface $input, string $name, int $min): ?int
    {
        $value = $input->getOption($name);
        if ($value === null) {
            return null;
        }

        $text = ScalarCoercion::toString($value);
        if (!ctype_digit($text) || (int) $text < $min) {
            throw new InvalidArgumentException(sprintf('--%s must be a whole number of at least %d, got "%s".', $name, $min, $text));
        }

        return (int) $text;
    }

    /**
     * @throws InvalidArgumentException when the value is not a number of at least `$min`
     *
     * @return ?float null when the option has no value and no default
     */
    public static function number(InputInterface $input, string $name, float $min): ?float
    {
        $value = $input->getOption($name);
        if ($value === null) {
            return null;
        }

        $text = ScalarCoercion::toString($value);
        if (!is_numeric($text) || (float) $text < $min) {
            throw new InvalidArgumentException(sprintf('--%s must be a number of at least %s, got "%s".', $name, $min, $text));
        }

        return (float) $text;
    }
}
