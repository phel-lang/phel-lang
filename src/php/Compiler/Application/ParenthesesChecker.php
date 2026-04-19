<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Lexer\TokenStream;

final readonly class ParenthesesChecker
{
    public function hasBalancedParentheses(TokenStream $tokenStream): bool
    {
        $parens = 0;
        $brackets = 0;
        $braces = 0;

        foreach ($tokenStream as $token) {
            switch ($token->getType()) {
                case Token::T_OPEN_PARENTHESIS:
                case Token::T_FN:
                case Token::T_HASH_FN:
                case Token::T_READER_COND:
                case Token::T_READER_COND_SPLICING:
                    ++$parens;
                    break;
                case Token::T_CLOSE_PARENTHESIS:
                    --$parens;
                    break;
                case Token::T_OPEN_BRACKET:
                    ++$brackets;
                    break;
                case Token::T_CLOSE_BRACKET:
                    --$brackets;
                    break;
                case Token::T_OPEN_BRACE:
                case Token::T_HASH_OPEN_BRACE:
                    ++$braces;
                    break;
                case Token::T_CLOSE_BRACE:
                    --$braces;
                    break;
            }
        }

        return $parens <= 0 && $brackets <= 0 && $braces <= 0;
    }
}
