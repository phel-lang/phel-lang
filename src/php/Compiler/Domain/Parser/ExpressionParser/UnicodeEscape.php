<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;

use function chr;

/**
 * @internal
 */
final class UnicodeEscape
{
    public static function encode(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10FFFF || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF)) {
            throw new StringParserException('Invalid UTF-8 codepoint escape sequence: Not a Unicode scalar');
        }

        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }

        if ($codepoint <= 0x7FF) {
            return chr(($codepoint >> 6) + 0xC0) . chr(($codepoint & 0x3F) + 0x80);
        }

        if ($codepoint <= 0xFFFF) {
            return chr(($codepoint >> 12) + 0xE0) . chr((($codepoint >> 6) & 0x3F) + 0x80) . chr(($codepoint & 0x3F) + 0x80);
        }

        return chr(($codepoint >> 18) + 0xF0) . chr((($codepoint >> 12) & 0x3F) + 0x80)
            . chr((($codepoint >> 6) & 0x3F) + 0x80) . chr(($codepoint & 0x3F) + 0x80);
    }
}
