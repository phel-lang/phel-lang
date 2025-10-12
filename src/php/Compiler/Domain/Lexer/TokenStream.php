<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Lexer;

use Generator;
use Iterator;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use RuntimeException;

use function count;
use function in_array;

/**
 * @template-implements Iterator<mixed, Token>
 */
final class TokenStream implements Iterator
{
    /** @var list<Token> */
    private array $readTokens = [];

    /**
     * @param Generator<?Token> $tokenGenerator
     */
    public function __construct(
        private readonly Generator $tokenGenerator,
    ) {
        $current = $this->tokenGenerator->current();
        if ($current instanceof Token) {
            $this->readTokens[] = $current;
        }
    }

    public function next(): void
    {
        $this->tokenGenerator->next();
        $currentToken = $this->tokenGenerator->current();

        if ($currentToken instanceof Token) {
            $this->readTokens[] = $currentToken;
        }
    }

    public function key(): mixed
    {
        return $this->tokenGenerator->key();
    }

    public function rewind(): void
    {
        $this->tokenGenerator->rewind();
    }

    public function current(): Token
    {
        $current = $this->tokenGenerator->current();

        if (!$current instanceof Token) {
            throw new RuntimeException('Token generator exhausted unexpectedly.');
        }

        return $current;
    }

    public function valid(): bool
    {
        return $this->tokenGenerator->valid();
    }

    public function clearReadTokens(): void
    {
        $this->readTokens = [];
        $current = $this->tokenGenerator->current();

        if ($current instanceof Token) {
            $this->readTokens[] = $current;
        }
    }

    public function getReadTokens(): array
    {
        return $this->readTokens;
    }

    public function getCodeSnippet(): CodeSnippet
    {
        $tokens = $this->removeLeadingWhitespace($this->readTokens);
        $code = $this->getCode($tokens);

        return new CodeSnippet(
            $tokens[0]->getStartLocation(),
            $tokens[count($tokens) - 1]->getEndLocation(),
            $code,
        );
    }

    /**
     * @param list<Token> $readTokens
     */
    private function removeLeadingWhitespace(array $readTokens): array
    {
        $result = [];
        $leadingWhitespace = true;
        foreach ($readTokens as $token) {
            if (!($leadingWhitespace
                && in_array($token->getType(), [Token::T_WHITESPACE, Token::T_COMMENT, Token::T_COMMENT_MACRO], true))
            ) {
                $leadingWhitespace = false;
                $result[] = $token;
            }
        }

        return $result;
    }

    /**
     * Concatenates all Tokens to a string.
     *
     * @param list<Token> $readTokens The tokens read so far
     */
    private function getCode(array $readTokens): string
    {
        $code = '';
        foreach ($readTokens as $token) {
            $code .= $token->getCode();
        }

        return $code;
    }
}
