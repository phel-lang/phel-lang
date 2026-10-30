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
 * @internal
 */
final class CharParser
{
    /** The widest value chr() accepts; see StringParser::MAX_BYTE. */
    private const int MAX_BYTE = 0xFF;

    private const int MAX_CODEPOINT = 0xFFFF;

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
        if (strlen($raw) === 6 && $raw[1] === 'u') {
            return $this->codepointToUtf8((int) hexdec(substr($raw, 2)));
        }

        // Octal escape: \oNNN (1-3 octal digits, validated by the lexer).
        // The lexer's 1-3 digit rule admits \o400 through \o777, which are not
        // bytes; chr() would silently wrap them with % 256.
        if (strlen($raw) >= 3 && $raw[1] === 'o' && preg_match('/^[0-7]{1,3}$/', substr($raw, 2)) === 1) {
            $octal = (int) octdec(substr($raw, 2));

            if ($octal < 0 || $octal > self::MAX_BYTE) {
                throw new StringParserException(
                    sprintf('Octal escape sequence out of range: \\o%s is above \\o377.', substr($raw, 2)),
                );
            }

            return chr($octal);
        }

        // Single-character literal: the char immediately following the backslash.
        return substr($raw, 1);
    }

    private function codepointToUtf8(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > self::MAX_CODEPOINT || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF)) {
            throw new StringParserException('Invalid UTF-8 codepoint escape sequence: Not a Unicode scalar');
        }

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
