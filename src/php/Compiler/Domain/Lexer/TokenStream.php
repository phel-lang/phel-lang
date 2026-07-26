<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Lexer;

use Generator;
use Iterator;
use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\Token;
use Phel\Shared\Parser\ReadModel\CodeSnippet;
use RuntimeException;

use function count;
use function in_array;

/**
 * @template-implements Iterator<int, Token>
 *
 * @internal
 */
final class TokenStream implements Iterator
{
    /** @var list<Token> */
    private array $readTokens = [];

    /**
     * @param Generator<int, ?Token> $tokenGenerator
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

    public function key(): int
    {
        return $this->tokenGenerator->key();
    }

    public function rewind(): void
    {
        $this->tokenGenerator->rewind();
    }

    /**
     * @phpstan-impure
     */
    public function current(): Token
    {
        $current = $this->tokenGenerator->current();

        if (!$current instanceof Token) {
            throw new RuntimeException('Token generator exhausted unexpectedly.');
        }

        return $current;
    }

    /**
     * @phpstan-impure
     */
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

    /**
     * The code read since the last {@see self::clearReadTokens()}, for an error
     * message pointing at the offending form.
     *
     * This runs while another error is already being reported, so it must not
     * add a second failure of its own. Two inputs leave it with no significant
     * token to anchor on: a stream whose read tokens are all trivia (a
     * whitespace-, comment- or `#_`-only source, which
     * {@see self::removeLeadingWhitespace()} strips to nothing) and a stream
     * that has been advanced past its end (`clearReadTokens()` then finds no
     * current token). Both used to index `$tokens[0]` on an empty array and
     * die with "Call to a member function getStartLocation() on null" instead
     * of reporting the original error. They now degrade: first to the trivia
     * itself, which still carries real locations, then to an empty snippet at
     * an unknown location.
     */
    public function getCodeSnippet(): CodeSnippet
    {
        $tokens = $this->removeLeadingWhitespace($this->readTokens);
        if ($tokens === []) {
            $tokens = $this->readTokens;
        }

        $first = $tokens[0] ?? null;
        $last = $tokens[count($tokens) - 1] ?? null;

        if (!$first instanceof Token || !$last instanceof Token) {
            return new CodeSnippet(SourceLocation::unknown(), SourceLocation::unknown(), '');
        }

        return new CodeSnippet(
            $first->getStartLocation(),
            $last->getEndLocation(),
            $this->getCode($tokens),
        );
    }

    /**
     * @param list<Token> $readTokens
     *
     * @return list<Token>
     */
    private function removeLeadingWhitespace(array $readTokens): array
    {
        $result = [];
        $leadingWhitespace = true;
        foreach ($readTokens as $token) {
            if (!$leadingWhitespace || !in_array($token->getType(), [Token::T_WHITESPACE, Token::T_COMMENT, Token::T_COMMENT_MACRO], true)
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
