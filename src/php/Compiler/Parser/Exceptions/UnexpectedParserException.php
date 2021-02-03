<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\Exceptions;

use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;

final class UnexpectedParserException extends AbstractParserException
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
