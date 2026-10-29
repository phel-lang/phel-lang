<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;
use Phel\Shared\Parser\Node\StringNode;
use Phel\Shared\Parser\Node\Token;

use function chr;
use function sprintf;
use function strlen;

/**
 * @internal
 */
final class StringParser
{
    private const string ESCAPE_SEQUENCE_PATTERN = '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\}|u([0-9a-fA-F]{4})(?:\\\\u([dD][c-fC-F][0-9a-fA-F]{2}))?)~';

    /**
     * The widest value chr() accepts. Above it PHP 8.5 deprecates the call and
     * silently constrains the value with % 256, which changes the character.
     */
    private const int MAX_BYTE = 0xFF;

    private const int BRACED_UNICODE_ESCAPE_MATCH = 2;

    private const int FIXED_UNICODE_ESCAPE_MATCH = 3;

    private const int LOW_SURROGATE_ESCAPE_MATCH = 4;

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
            return chr(((int) hexdec(substr($sequence, 1))) & self::MAX_BYTE);
        }

        if ($sequence[0] === 'u') {
            return $this->parseUnicodeEscape($matches);
        }

        $octal = (int) octdec($sequence);

        if ($octal < 0 || $octal > self::MAX_BYTE) {
            throw new StringParserException(
                sprintf('Octal escape sequence out of range: \\%s is above \\377.', $sequence),
            );
        }

        return chr($octal);
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

        $significantDigits = ltrim($hexCodepoint, '0');
        if (strlen($significantDigits) > 6) {
            throw new StringParserException('Invalid UTF-8 codepoint escape sequence: Codepoint too large');
        }

        $codepoint = (int) hexdec($significantDigits === '' ? '0' : $significantDigits);
        $lowSurrogate = $matches[self::LOW_SURROGATE_ESCAPE_MATCH] ?? '';
        if ($lowSurrogate !== '') {
            if ($codepoint < 0xD800 || $codepoint > 0xDBFF) {
                throw new StringParserException('Invalid UTF-8 codepoint escape sequence: Unexpected low surrogate');
            }

            $codepoint = 0x10000 + (($codepoint - 0xD800) << 10) + ((int) hexdec($lowSurrogate) - 0xDC00);
        }

        return UnicodeEscape::encode($codepoint);
    }
}
