<?php

declare(strict_types=1);

namespace Phel\Shared;

use RuntimeException;

use function ini_set;
use function var_export;

/**
 * @internal
 */
final class FloatFormatter
{
    public static function format(float $value): string
    {
        $precision = ini_set('serialize_precision', '-1');
        if ($precision === false) {
            throw new RuntimeException('Cannot configure float literal precision');
        }

        try {
            return var_export($value, true);
        } finally {
            ini_set('serialize_precision', $precision);
        }
    }
}
