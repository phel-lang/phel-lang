<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Lang\SourceLocation;

final class ParserException extends PhelCodeException
{
    private CodeSnippet $codeSnippet;

    public static function forSnippet(CodeSnippet $snippet, Token $token, string $message): self
    {
        return new self(
            $message,
            $snippet,
            $token->getStartLocation(),
            $token->getEndLocation()
        );
    }

    public function __construct(
        string $message,
        CodeSnippet $codeSnippet,
        SourceLocation $startLocation,
        SourceLocation $endLocation
    ) {
        parent::__construct($message, $startLocation, $endLocation);
        $this->codeSnippet = $codeSnippet;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        return $this->codeSnippet;
    }
}
