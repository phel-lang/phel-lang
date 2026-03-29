<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\ParserNode\StringNode;

/**
 * Parses regex literal tokens (#"pattern") into StringNode values
 * containing PCRE-compatible delimited patterns ("/pattern/").
 */
final class RegexParser
{
    public function parse(Token $token): StringNode
    {
        // Strip #" prefix and " suffix, unescape quotes only
        $raw = substr($token->getCode(), 2, -1);
        $pattern = str_replace('\\"', '"', $raw);

        // Escape unescaped forward slashes so /delimiter/ is never broken
        $pattern = preg_replace('/(?<!\\\\)\\//', '\\/', $pattern) ?? $pattern;

        return new StringNode(
            $token->getCode(),
            $token->getStartLocation(),
            $token->getEndLocation(),
            '/' . $pattern . '/',
        );
    }
}
