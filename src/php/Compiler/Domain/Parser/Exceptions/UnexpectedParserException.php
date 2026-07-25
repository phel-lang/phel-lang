<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\Exceptions;

use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

final class UnexpectedParserException extends AbstractParserException
{
    /**
     * `$nestedException` keeps the sub-parser failure that produced `$message`
     * reachable via `getPrevious()`; the located output is unchanged.
     */
    public static function forSnippet(
        CodeSnippet $snippet,
        Token $token,
        string $message,
        ?ErrorCode $errorCode = null,
        ?Throwable $nestedException = null,
    ): self {
        $e = new self(
            $message,
            $snippet,
            $token->getStartLocation(),
            $token->getEndLocation(),
            $nestedException,
        );

        if ($errorCode instanceof ErrorCode) {
            $e->setErrorCode($errorCode);
        }

        return $e;
    }
}
