<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;

use function chr;
use function hexdec;
use function octdec;
use function sprintf;
use function strlen;
use function substr;

/**
 * Decodes Clojure-style character literal tokens produced by the lexer into
 * single-character `StringNode` values. PHP has no distinct character type, so
 * char literals compile to single-character PHP strings (UTF-8 encoded for
 * Unicode escapes).
 *
 * Assumes the token source matches the lexer's char-literal rule: that is,
 * `\<named>`, `\u<4 hex>`, `\o<1-3 octal>`, or `\<single char>`. The lexer
 * handles shape; this parser decodes and additionally rejects an octal escape
 * whose value is not a byte, which the lexer's 1-3 digit rule cannot express.
 *
 * @internal
 */
final class CharParser
{
    /** The widest value chr() accepts; see StringParser::MAX_BYTE. */
    private const int MAX_BYTE = 0xFF;

    private const array NAMED_CHARS = [
        '\\space' => ' ',
        '\\newline' => "\n",
        '\\tab' => "\t",
        '\\formfeed' => "\f",
        '\\backspace' => "\x08",
        '\\return' => "\r",
    ];

    /**
     * @throws StringParserException
     */
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
        // The lexer's 1-3 digit rule admits \o400 through \o777, which are not
        // bytes; chr() would silently wrap them with % 256.
        if (strlen($raw) >= 3 && $raw[1] === 'o' && preg_match('/^[0-7]{1,3}$/', substr($raw, 2)) === 1) {
            $octal = (int) octdec(substr($raw, 2));

            if ($octal > self::MAX_BYTE) {
                throw new StringParserException(
                    sprintf('Octal escape sequence out of range: \\o%s is above \\o377.', substr($raw, 2)),
                );
            }

            return chr($octal);
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
            return chr($codepoint & self::MAX_BYTE);
        }

        if ($codepoint <= 0x7FF) {
            return chr((($codepoint >> 6) + 0xC0) & self::MAX_BYTE)
                . chr((($codepoint & 0x3F) + 0x80) & self::MAX_BYTE);
        }

        return chr((($codepoint >> 12) + 0xE0) & self::MAX_BYTE)
            . chr(((($codepoint >> 6) & 0x3F) + 0x80) & self::MAX_BYTE)
            . chr((($codepoint & 0x3F) + 0x80) & self::MAX_BYTE);
    }
}
