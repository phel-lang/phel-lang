<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Lexer\TokenStream;

final readonly class ParenthesesChecker
{
    public function hasBalancedParentheses(TokenStream $tokenStream): bool
    {
        $open = 0;
        $close = 0;

        foreach ($tokenStream as $token) {
            if ($token->getType() === Token::T_OPEN_PARENTHESIS) {
                ++$open;
            } elseif ($token->getType() === Token::T_CLOSE_PARENTHESIS) {
                ++$close;
            }
        }

        return $close >= $open;
    }
}
