<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\ParserNode\StringNode;

use function chr;
use function hexdec;
use function octdec;
use function strlen;
use function substr;

/**
 * Decodes Clojure-style character literal tokens produced by the lexer into
 * single-character `StringNode` values. PHP has no distinct character type, so
 * char literals compile to single-character PHP strings (UTF-8 encoded for
 * Unicode escapes).
 *
 * Assumes the token source matches the lexer's char-literal rule: that is,
 * `\<named>`, `\u<4 hex>`, `\o<1-3 octal>`, or `\<single char>`. The lexer is
 * the single source of validation; this parser only decodes.
 */
final class CharParser
{
    private const array NAMED_CHARS = [
        '\\space' => ' ',
        '\\newline' => "\n",
        '\\tab' => "\t",
        '\\formfeed' => "\f",
        '\\backspace' => "\x08",
        '\\return' => "\r",
    ];

    public function parse(Token $token): StringNode
    {
        return new StringNode(
            $token->getCode(),
            $token->getStartLocation(),
            $token->getEndLocation(),
            $this->decode($token->getCode()),
        );
    }

    private function decode(string $raw): string
    {
        if (isset(self::NAMED_CHARS[$raw])) {
            return self::NAMED_CHARS[$raw];
        }

        // Unicode escape: \uNNNN (exactly 4 hex digits, validated by the lexer).
        // 4 hex digits yields a codepoint in [0, 0xFFFF], always representable as UTF-8.
        if (strlen($raw) === 6 && $raw[1] === 'u') {
            return $this->codepointToUtf8((int) hexdec(substr($raw, 2)));
        }

        // Octal escape: \oNNN (1-3 octal digits, validated by the lexer).
        if (strlen($raw) >= 3 && $raw[1] === 'o' && preg_match('/^[0-7]{1,3}$/', substr($raw, 2)) === 1) {
            return chr((int) octdec(substr($raw, 2)));
        }

        // Single-character literal: the char immediately following the backslash.
        return substr($raw, 1);
    }

    /**
     * Encodes a Unicode codepoint in [0, 0xFFFF] as a UTF-8 byte sequence.
     * The input range is guaranteed by the `\uNNNN` lexer rule (exactly 4 hex digits).
     */
    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint <= 0x7F) {
            return chr($codepoint);
        }

        if ($codepoint <= 0x7FF) {
            return chr(($codepoint >> 6) + 0xC0) . chr(($codepoint & 0x3F) + 0x80);
        }

        return chr(($codepoint >> 12) + 0xE0)
            . chr((($codepoint >> 6) & 0x3F) + 0x80)
            . chr(($codepoint & 0x3F) + 0x80);
    }
}
