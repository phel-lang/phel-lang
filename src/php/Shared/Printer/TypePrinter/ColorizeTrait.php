<?php

declare(strict_types=1);

namespace Phel\Shared\Printer\TypePrinter;

use function sprintf;

/**
 * Wraps a string in an ANSI colour escape when colour output is enabled.
 * Each printer keeps its own SGR code in a `COLOR` constant; only the escape
 * scaffolding is shared here.
 *
 * Using printers must expose the `$withColor` property (via WithColorTrait or
 * their own constructor).
 *
 * @property-read bool $withColor
 */
trait ColorizeTrait
{
    private function colorize(string $str, string $code): string
    {
        if ($this->withColor) {
            return sprintf("\033[%sm%s\033[0m", $code, $str);
        }

        return $str;
    }
}
