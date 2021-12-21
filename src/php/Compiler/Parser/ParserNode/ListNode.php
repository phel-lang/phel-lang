<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ParserNode;

use Phel\Compiler\Lexer\Token;
use Phel\Lang\SourceLocation;

final class ListNode implements InnerNodeInterface
{
    private int $tokenType;
    private SourceLocation $startLocation;
    private SourceLocation $endLocation;
    /** @var list<NodeInterface> */
    private array $children;

    public function __construct(
        int $tokenType,
        SourceLocation $startLocation,
        SourceLocation $endLocation,
        array $children
    ) {
        $this->tokenType = $tokenType;
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
        $this->children = $children;
    }

    /**
     * @return list<NodeInterface>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @param list<NodeInterface> $children
     */
    public function replaceChildren(array $children): InnerNodeInterface
    {
        $this->children = $children;

        return $this;
    }

    public function getCode(): string
    {
        $code = '';
        foreach ($this->children as $child) {
            $code .= $child->getCode();
        }

        return $this->getCodePrefix() . $code . $this->getCodePostfix();
    }

    public function getCodePrefix(): string
    {
        switch ($this->tokenType) {
            case Token::T_OPEN_PARENTHESIS:
                return '(';
            case Token::T_FN:
                return '|(';
            case Token::T_OPEN_BRACKET:
                return '[';
            case Token::T_OPEN_BRACE:
                return '{';
            default:
                throw new \RuntimeException('Cannot find code prefix for token type: ' . $this->tokenType);
        }
    }

    public function getCodePostfix(): ?string
    {
        switch ($this->tokenType) {
            case Token::T_OPEN_PARENTHESIS:
            case Token::T_FN:
                return ')';
            case Token::T_OPEN_BRACKET:
                return ']';
            case Token::T_OPEN_BRACE:
                return '}';
            default:
                throw new \RuntimeException('Cannot find code prefix for token type: ' . $this->tokenType);
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
