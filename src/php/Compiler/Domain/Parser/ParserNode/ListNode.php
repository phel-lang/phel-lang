<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ParserNode;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Lang\SourceLocation;
use RuntimeException;

final class ListNode implements InnerNodeInterface
{
    /**
     * @param list<NodeInterface> $children
     */
    public function __construct(
        private readonly int $tokenType,
        private readonly SourceLocation $startLocation,
        private readonly SourceLocation $endLocation,
        private array $children,
    ) {
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

        return $this->getCodePrefix() . $code . ($this->getCodePostfix() ?? '');
    }

    public function getCodePrefix(): string
    {
        return match ($this->tokenType) {
            Token::T_OPEN_PARENTHESIS => '(',
            Token::T_FN => '|(',
            Token::T_OPEN_BRACKET => '[',
            Token::T_OPEN_BRACE => '{',
            default => throw new RuntimeException('Cannot find code prefix for token type: ' . $this->tokenType),
        };
    }

    public function getCodePostfix(): ?string
    {
        return match ($this->tokenType) {
            Token::T_OPEN_PARENTHESIS, Token::T_FN => ')',
            Token::T_OPEN_BRACKET => ']',
            Token::T_OPEN_BRACE => '}',
            default => throw new RuntimeException('Cannot find code prefix for token type: ' . $this->tokenType),
        };
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
