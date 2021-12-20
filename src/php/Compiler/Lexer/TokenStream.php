<?php

declare(strict_types=1);

namespace Phel\Compiler\Lexer;

use Generator;
use Iterator;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;

final class TokenStream implements Iterator
{
    /** @var Generator<Token> */
    private Generator $tokenGenerator;

    /** @var Token[] */
    private array $readTokens;

    public function __construct(Generator $tokenGenerator)
    {
        $this->tokenGenerator = $tokenGenerator;
        $this->readTokens = [$tokenGenerator->current()];
    }

    public function next(): void
    {
        $this->tokenGenerator->next();
        $this->readTokens[] = $this->tokenGenerator->current();
    }

    public function key(): mixed
    {
        return $this->tokenGenerator->key();
    }

    public function rewind(): void
    {
        $this->tokenGenerator->rewind();
    }

    /**
     * @return Token
     */
    public function current(): mixed
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
            $code
        );
    }

    /**
     * @param Token[] $readTokens
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
     * @param Token[] $readTokens The tokens read so far
     */
    private function getCode(array $readTokens): string
    {
        return implode(array_map(
            static fn (Token $t) => $t->getCode(),
            $readTokens
        ));
    }
}
