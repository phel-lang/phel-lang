<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\Exceptions;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;

final class UnfinishedParserException extends AbstractParserException
{
    public static function forSnippet(CodeSnippet $snippet, Token $token, string $message): self
    {
        return new self(
            $message,
            $snippet,
            $token->getStartLocation(),
            $token->getEndLocation(),
        );
    }
}
