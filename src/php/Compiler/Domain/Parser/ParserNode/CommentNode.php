<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Lang\SourceLocation;

final class CommentNode implements TriviaNodeInterface
{
    private string $code;
    private SourceLocation $startLocation;
    private SourceLocation $endLocation;

    public function __construct(
        string $code,
        SourceLocation $startLocation,
        SourceLocation $endLocation
    ) {
        $this->code = $code;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
    }

    public static function createWithToken(Token $token): self
    {
        return new self(
            $token->getCode(),
            $token->getStartLocation(),
            $token->getEndLocation()
        );
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }
}
