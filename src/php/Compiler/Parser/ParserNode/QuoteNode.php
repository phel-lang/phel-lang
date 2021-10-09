<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ParserNode;

use Phel\Compiler\Lexer\Token;
use Phel\Lang\SourceLocation;

final class QuoteNode implements InnerNodeInterface
{
    private int $tokenType;
    private SourceLocation $startLocation;
    private SourceLocation $endLocation;
    private NodeInterface $expression;

    public function __construct(
        int $tokenType,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
        NodeInterface $expression
    ) {
        $this->tokenType = $tokenType;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
        $this->expression = $expression;
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
        switch ($this->tokenType) {
            case Token::T_QUOTE:
                return "'";
            case Token::T_UNQUOTE:
                return ',';
            case Token::T_UNQUOTE_SPLICING:
                return ',@';
            case Token::T_QUASIQUOTE:
                return '`';
            default:
                throw new \RuntimeException('Cannot find code prefix for token type: ' . $this->tokenType);
        }
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
