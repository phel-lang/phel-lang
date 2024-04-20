<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Parser\ParserNode;

use Phel\Lang\SourceLocation;
use Phel\Transpiler\Domain\Lexer\Token;

final readonly class NewlineNode implements TriviaNodeInterface
{
    public function __construct(
        private string $code,
        private SourceLocation $startLocation,
        private SourceLocation $endLocation,
    ) {
    }

    public static function createWithToken(Token $token): self
    {
        return new self(
            $token->getCode(),
            $token->getStartLocation(),
            $token->getEndLocation(),
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
