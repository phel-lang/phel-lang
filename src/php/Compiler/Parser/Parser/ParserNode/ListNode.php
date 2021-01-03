<?php

namespace Phel\Compiler\Parser\Parser\ParserNode;

use Phel\Compiler\Lexer\Token;
use Phel\Lang\SourceLocation;

final class ListNode implements InnerNodeInterface
{
    private int $tokenType;
    /** @var NodeInterface[] */
    private array $children;
    private SourceLocation $startLocation;
    private SourceLocation $endLocation;

    public function __construct(int $tokenType, SourceLocation $startLocation, SourceLocation $endLocation, array $children)
    {
        $this->tokenType = $tokenType;
        $this->children = $children;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getCode(): string
    {
        $code = '';
        foreach ($this->children as $child) {
            $code .= $child->getCode();
        }

        return $this->getCodePrefix() . $code . $this->getCodePostfix();
    }

    private function getCodePrefix(): string
    {
        switch ($this->tokenType) {
            case Token::T_OPEN_PARENTHESIS:
                return '(';
            case Token::T_OPEN_BRACKET:
                return '[';
            case Token::T_OPEN_BRACE:
                return '{';
            case Token::T_FN:
                return '|(';
            case Token::T_ARRAY:
                return '@[';
            case Token::T_TABLE:
                return '@{';
            default:
                throw new \RuntimeException('Can not find code prefix for token type: ' . $this->tokenType);
        }
    }

    private function getCodePostfix(): string
    {
        switch ($this->tokenType) {
            case Token::T_OPEN_PARENTHESIS:
            case Token::T_FN:
                return ')';
            case Token::T_OPEN_BRACKET:
            case Token::T_ARRAY:
                return ']';
            case Token::T_OPEN_BRACE:
            case Token::T_TABLE:
                return '}';
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
}
