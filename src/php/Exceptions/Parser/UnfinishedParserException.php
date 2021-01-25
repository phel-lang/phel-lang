<?php

declare(strict_types=1);

namespace Phel\Exceptions\Parser;

use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Exceptions\ParserException;

class UnfinishedParserException extends ParserException
{
    public static function forSnippet(CodeSnippet $snippet, Token $token, string $message): self
    {
        return new self(
            $message,
            $snippet,
            $token->getStartLocation(),
            $token->getEndLocation()
        );
    }
}
