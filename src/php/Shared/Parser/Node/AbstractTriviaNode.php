<?php

declare(strict_types=1);

namespace Phel\Shared\Parser\Node;

use Phel\Lang\SourceLocation;

/**
 * Shared shape of the token-backed trivia nodes (whitespace, newline, comma,
 * comment): the verbatim source text plus its span. Subclasses exist only to
 * keep the categories distinguishable via `instanceof`.
 */
abstract readonly class AbstractTriviaNode implements TriviaNodeInterface
{
    final public function __construct(
        private string $code,
        private SourceLocation $startLocation,
        private SourceLocation $endLocation,
    ) {}

    public static function createWithToken(Token $token): static
    {
        return new static(
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
