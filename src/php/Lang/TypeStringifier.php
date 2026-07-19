<?php

declare(strict_types=1);

namespace Phel\Lang;

use Phel\Shared\Printer\Printer;
use Throwable;

use function get_debug_type;

final class TypeStringifier
{
    public static function toString(TypeInterface $value): string
    {
        try {
            return Printer::readable()->print($value);
        } catch (Throwable) {
            return '#object[' . get_debug_type($value) . ']';
        }
    }
}
