<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Shared\Parser\Node\Token;

/**
 * @internal
 */
final readonly class ParenthesesChecker
{
    public function hasBalancedParentheses(TokenStream $tokenStream): bool
    {
        $parens = 0;
        $brackets = 0;
        $braces = 0;

        try {
            foreach ($tokenStream as $token) {
                switch ($token->getType()) {
                    case Token::T_OPEN_PARENTHESIS:
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
        } catch (LexerValueException) {
            // Input that does not lex has no delimiter count to report, and
            // the same reasoning as below applies: report it as ready so the
            // compile path raises the located lexer error, with its snippet
            // and caret, instead of a bare "Unbalanced parentheses." (#3289).
            return true;
        }

        // A closer with no matching opener can never be repaired by appending
        // more input, so the input is malformed rather than incomplete. Report
        // it as ready and let the parser raise a located error. Otherwise the
        // REPL, which buffers until this returns true, waits forever on a
        // mistyped bracket: `(php/+ 1` then `]` leaves parens at 1 and brackets
        // at -1, so the user gets no feedback at all and has to Ctrl-D out.
        if ($parens < 0 || $brackets < 0 || $braces < 0) {
            return true;
        }

        return $parens <= 0 && $brackets <= 0 && $braces <= 0;
    }
}
