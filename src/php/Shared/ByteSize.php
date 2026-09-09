<?php

declare(strict_types=1);

namespace Phel\Shared;

use function sprintf;

/**
 * Renders a byte count for human eyes, two decimals, largest fitting unit.
 *
 * Shared so the CLI reports one size format: `phel build --report` and
 * `phel doctor` print the same string for the same number of bytes.
 */
final class ByteSize
{
    private const array UNITS = [
        'GB' => 1_073_741_824,
        'MB' => 1_048_576,
        'KB' => 1024,
    ];

    public static function format(int $bytes): string
    {
        foreach (self::UNITS as $unit => $size) {
            if ($bytes >= $size) {
                return sprintf('%.2f %s', $bytes / $size, $unit);
            }
        }

        return $bytes . ' B';
    }
}
