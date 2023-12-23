<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Lang\SourceLocation;
use RuntimeException;

final class QuoteNode implements InnerNodeInterface
{
    public function __construct(
        private readonly int $tokenType,
        private readonly SourceLocation $startLocation,
        private readonly SourceLocation $endLocation,
        private NodeInterface $expression,
    ) {
    }

    /**
     * @return list<NodeInterface>
     */
    public function getChildren(): array
    {
        return [$this->expression];
    }

    /**
     * @param list<NodeInterface> $children
     */
    public function replaceChildren(array $children): InnerNodeInterface
    {
        $this->expression = $children[0];

        return $this;
    }

    public function getCode(): string
    {
        return $this->getCodePrefix() . $this->expression->getCode();
    }

    public function getCodePrefix(): string
    {
        return match ($this->tokenType) {
            Token::T_QUOTE => "'",
            Token::T_UNQUOTE => ',',
            Token::T_UNQUOTE_SPLICING => ',@',
            Token::T_QUASIQUOTE => '`',
            default => throw new RuntimeException('Cannot find code prefix for token type: ' . $this->tokenType),
        };
    }

    public function getCodePostfix(): ?string
    {
        return null;
    }

    public function getStartLocation(): SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): SourceLocation
    {
        return $this->endLocation;
    }

    public function getTokenType(): int
    {
        return $this->tokenType;
    }

    public function getExpression(): NodeInterface
    {
        return $this->expression;
    }
}
