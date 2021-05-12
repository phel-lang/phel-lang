<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\Exceptions\StringParserException;
use Phel\Compiler\Parser\ParserNode\StringNode;

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
            $this->parseEscapedString(substr($token->getCode(), 1, -1))
        );
    }

    private function parseEscapedString(string $str): string
    {
        $str = str_replace('\\"', '"', $str);

        return preg_replace_callback(
            '~\\\\([\\\\$nrtfve]|[xX][0-9a-fA-F]{1,2}|[0-7]{1,3}|u\{([0-9a-fA-F]+)\})~',
            function (array $matches): string {
                $str = $matches[1];

                if (isset(self::STRING_REPLACEMENTS[$str])) {
                    return self::STRING_REPLACEMENTS[$str];
                }

                if ('x' === $str[0] || 'X' === $str[0]) {
                    return chr(hexdec(substr($str, 1)));
                }

                if ('u' === $str[0]) {
                    return self::codePointToUtf8(hexdec($matches[2]));
                }

                /** @var int $n */
                $n = octdec($str);
                return chr($n);
            },
            $str
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
