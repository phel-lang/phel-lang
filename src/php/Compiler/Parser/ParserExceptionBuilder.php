<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Compiler\Token;
use Phel\Exceptions\ParserException;

final class ParserExceptionBuilder
{
    /** @var Token[] */
    private array $readTokens;

    /**
     * @param Token[] $readTokens
     */
    private function __construct(array $readTokens)
    {
        $this->readTokens = $readTokens;
    }

    /**
     * @param Token[] $readTokens
     */
    public static function withReadTokens(array $readTokens): self
    {
        return new self($readTokens);
    }

    public function build(string $message): ParserException
    {
        $codeSnippet = $this->getCodeSnippet($this->readTokens);

        return new ParserException(
            $message,
            $codeSnippet->getStartLocation(),
            $codeSnippet->getEndLocation(),
            $codeSnippet
        );
    }

    /**
     * Create a CodeSnippet from a list of Tokens.
     *
     * @param Token[] $readTokens The tokens read so far
     */
    private function getCodeSnippet($readTokens): CodeSnippet
    {
        $tokens = $this->removeLeadingWhitespace($readTokens);
        $code = $this->getCode($tokens);

        return new CodeSnippet(
            $tokens[0]->getStartLocation(),
            $tokens[count($tokens) - 1]->getEndLocation(),
            $code
        );
    }

    /**
     * Removes all leading whitespace and comment tokens.
     *
     * @param Token[] $readTokens The tokens read so far
     *
     * @return Token[]
     */
    private function removeLeadingWhitespace($readTokens): array
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
