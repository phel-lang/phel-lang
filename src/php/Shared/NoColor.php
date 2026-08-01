<?php

declare(strict_types=1);

namespace Phel\Shared;

use function array_key_exists;
use function getenv;
use function is_string;

/**
 * Whether the environment asks for plain output.
 *
 * `NO_COLOR` is honoured the way <https://no-color.org> specifies: present and
 * non-empty, whatever the value. Symfony Console already obeys it for its own
 * output, so without this the two halves of a `phel` command disagree, and the
 * string-returning half of `ExceptionPrinterInterface` hands escape codes to a
 * caller that may be writing to a log or an error tracker (#2923).
 *
 * Public, like the rest of `Phel\Shared`: an embedder rendering Phel output of
 * its own should be able to make the same decision the same way.
 */
final class NoColor
{
    /**
     * @param array<string, mixed>|null $env defaults to the real environment
     */
    public static function isRequested(?array $env = null): bool
    {
        $value = $env === null
            ? getenv('NO_COLOR')
            : (array_key_exists('NO_COLOR', $env) ? $env['NO_COLOR'] : false);

        return is_string($value) && $value !== '';
    }

    /**
     * @param array<string, mixed>|null $env defaults to the real environment
     */
    public static function style(?array $env = null): ColorStyleInterface
    {
        return self::isRequested($env)
            ? ColorStyle::noStyles()
            : ColorStyle::withStyles();
    }
}
