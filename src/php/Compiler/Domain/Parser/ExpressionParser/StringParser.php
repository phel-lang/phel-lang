<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\Exceptions\StringParserException;
use Phel\Compiler\Domain\Parser\ParserNode\StringNode;

use function chr;

final class StringParser
{
    private const STRING_REPLACEMENTS = [
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
        $callback = function (array $matches): string {
            $str = $matches[1];

            if (isset(self::STRING_REPLACEMENTS[$str])) {
                return self::STRING_REPLACEMENTS[$str];
            }

            if ($str[0] === 'x' || $str[0] === 'X') {
                return chr(hexdec(substr((string) $str, 1)));
            }

            if ($str[0] === 'u') {
                return $this->codePointToUtf8(hexdec((string) $matches[2]));
            }

            return chr((int)octdec((string) $str));
        };

        return preg_replace_callback(
            '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\})~',
            $callback,
            str_replace('\\"', '"', $str),
        );
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
