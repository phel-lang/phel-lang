<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Lexer;

use Generator;
use Iterator;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;

use function count;
use function in_array;

/**
 * @template-implements Iterator<mixed, Token>
 */
final class TokenStream implements Iterator
{
    /** @var list<Token> */
    private array $readTokens;

    /**
     * @param Generator<Token> $tokenGenerator
     */
    public function __construct(
        private readonly Generator $tokenGenerator,
    ) {
        $this->readTokens = [$tokenGenerator->current()];
    }

    public function next(): void
    {
        $this->tokenGenerator->next();
        /** @var Token $currentToken */
        $currentToken = $this->tokenGenerator->current();
        $this->readTokens[] = $currentToken;
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
        return $this->tokenGenerator->current();
    }

    public function valid(): bool
    {
        return $this->tokenGenerator->valid();
    }

    public function clearReadTokens(): void
    {
        $this->readTokens = [$this->tokenGenerator->current()];
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
                && in_array($token->getType(), [Token::T_WHITESPACE, Token::T_COMMENT], true))
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
        return implode('', array_map(
            static fn (Token $t): string => $t->getCode(),
            $readTokens,
        ));
    }
}
