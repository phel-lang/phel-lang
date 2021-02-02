<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\Exceptions;

use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;

class UnfinishedParserException extends AbstractParserException
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
