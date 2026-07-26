<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;

use function chr;

/**
 * @internal
 */
final class StringParser
{
    private const string ESCAPE_SEQUENCE_PATTERN = '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\}|u([0-9a-fA-F]{4}))~';

    private const int BRACED_UNICODE_ESCAPE_MATCH = 2;

    private const int FIXED_UNICODE_ESCAPE_MATCH = 3;

    private const array STRING_REPLACEMENTS = [
        '\\' => '\\',
        '$' => '$',
        'n' => "\n",
        'r' => "\r",
        't' => "\t",
        'f' => "\f",
        'v' => "\v",
        'e' => "\x1B",
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
            $this->parseEscapedString(substr($token->getCode(), 1, -1)),
        );
    }

    private function parseEscapedString(string $str): string
    {
        $result = preg_replace_callback(
            self::ESCAPE_SEQUENCE_PATTERN,
            $this->replaceEscapeSequence(...),
            str_replace('\\"', '"', $str),
        );

        if ($result === null) {
            throw new StringParserException('Invalid escape sequence.');
        }

        return $result;
    }

    /**
     * @param array<int, string> $matches
     *
     * @throws StringParserException
     */
    private function replaceEscapeSequence(array $matches): string
    {
        $sequence = $matches[1];

        if (isset(self::STRING_REPLACEMENTS[$sequence])) {
            return self::STRING_REPLACEMENTS[$sequence];
        }

        if ($sequence[0] === 'x' || $sequence[0] === 'X') {
            return chr((int) hexdec(substr($sequence, 1)));
        }

        if ($sequence[0] === 'u') {
            return $this->parseUnicodeEscape($matches);
        }

        return chr((int) octdec($sequence));
    }

    /**
     * @param array<int, string> $matches
     *
     * @throws StringParserException
     */
    private function parseUnicodeEscape(array $matches): string
    {
        $hexCodepoint = $matches[self::BRACED_UNICODE_ESCAPE_MATCH] ?? '';

        if ($hexCodepoint === '') {
            $hexCodepoint = $matches[self::FIXED_UNICODE_ESCAPE_MATCH] ?? '';
        }

        return $this->codePointToUtf8((int) hexdec($hexCodepoint));
    }

    /**
     * @throws StringParserException
     */
    private function codePointToUtf8(int $num): string
    {
        if ($num <= 0x7F) {
            return chr($num);
        }

        if ($num <= 0x7FF) {
            return chr(($num >> 6) + 0xC0) . chr(($num & 0x3F) + 0x80);
        }

        if ($num <= 0xFFFF) {
            return chr(($num >> 12) + 0xE0) . chr((($num >> 6) & 0x3F) + 0x80) . chr(($num & 0x3F) + 0x80);
        }

        if ($num <= 0x1FFFFF) {
            return chr(($num >> 18) + 0xF0) . chr((($num >> 12) & 0x3F) + 0x80)
                . chr((($num >> 6) & 0x3F) + 0x80) . chr(($num & 0x3F) + 0x80);
        }

        throw new StringParserException('Invalid UTF-8 codepoint escape sequence: Codepoint too large');
    }
}
