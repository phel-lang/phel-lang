<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\Exceptions;

use Phel\Compiler\Domain\Exceptions\ErrorCode;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;

final class UnfinishedParserException extends AbstractParserException
{
    public static function forSnippet(CodeSnippet $snippet, Token $token, string $message, ?ErrorCode $errorCode = null): self
    {
        $e = new self(
            $message,
            $snippet,
            $token->getStartLocation(),
            $token->getEndLocation(),
        );

        if ($errorCode instanceof ErrorCode) {
            $e->setErrorCode($errorCode);
        }

        return $e;
    }
}
