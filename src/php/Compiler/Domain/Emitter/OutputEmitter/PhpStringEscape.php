<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use function strtr;

/**
 * String-escape helpers for emitting Phel symbol/keyword names into
 * generated PHP source.
 */
final class PhpStringEscape
{
    /**
     * Escape `$value` so it can be embedded inside a PHP double-quoted
     * literal (`"..."`) without losing characters.
     *
     * `addslashes` is wrong for double-quoted output because it escapes
     * the apostrophe with a backslash that PHP keeps verbatim inside
     * `"..."`, which silently mangles symbol/keyword names ending in
     * `'` (`inc'`, `dec'`, `+'`, `-'`, `*'`).
     */
    public static function doubleQuoted(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            '$' => '\\$',
        ]);
    }
}
