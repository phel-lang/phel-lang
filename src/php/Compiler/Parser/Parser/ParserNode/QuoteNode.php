<?php

namespace Phel\Compiler\Parser\Parser\ParserNode;

use Phel\Compiler\Token;
use Phel\Lang\SourceLocation;

final class QuoteNode implements InnerNodeInterface
{
    private int $tokenType;
    private NodeInterface $expression;
    private SourceLocation $startLocation;
    private SourceLocation $endLocation;

    public function __construct(int $tokenType, SourceLocation $startLocation, SourceLocation $endLocation, NodeInterface $expression)
    {
        $this->tokenType = $tokenType;
        $this->expression = $expression;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
    }

    public function getChildren(): array
    {
        return [$this->expression];
    }

    public function getCode(): string
    {
        return $this->getCodePrefix() . $this->expression->getCode();
    }

    private function getCodePrefix(): string
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
                throw new \RuntimeException('Can not find code prefix for token type: ' . $this->tokenType);
        }
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
