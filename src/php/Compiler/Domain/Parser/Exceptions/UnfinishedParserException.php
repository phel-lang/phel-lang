<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\Exceptions;

use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\ReadModel\CodeSnippet;

final class UnfinishedParserException extends AbstractParserException
{
    public static function forSnippet(CodeSnippet $snippet, Token $token, string $message, ?ErrorCode $errorCode = null): self
    {
        return self::build($message, $snippet, $token->getStartLocation(), $token->getEndLocation(), $errorCode);
    }

    /**
     * The variant for a stream that has run out of tokens, where there is no
     * `current()` token to point at.
     *
     * Asking the stream for one anyway raises "Token generator exhausted
     * unexpectedly" *while the real diagnostic is being built*, and the user
     * loses the message that would have told them what is wrong. Callers in
     * that position pass where the unfinished form opened instead, which is the
     * more useful anchor anyway: the missing bracket belongs to that line.
     */
    public static function forExhaustedStream(
        CodeSnippet $snippet,
        SourceLocation $startLocation,
        string $message,
        ?ErrorCode $errorCode = null,
    ): self {
        return self::build($message, $snippet, $startLocation, $snippet->getEndLocation(), $errorCode);
    }

    private static function build(
        string $message,
        CodeSnippet $snippet,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
        ?ErrorCode $errorCode,
    ): self {
        $e = new self($message, $snippet, $startLocation, $endLocation);

        if ($errorCode instanceof ErrorCode) {
            $e->setErrorCode($errorCode);
        }

        return $e;
    }
}
