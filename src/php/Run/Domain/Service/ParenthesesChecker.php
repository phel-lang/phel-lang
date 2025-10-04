<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Service;

use Phel\Compiler\CompilerFacadeInterface;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Run\Domain\ParenthesesCheckerInterface;

final readonly class ParenthesesChecker implements ParenthesesCheckerInterface
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {
    }

    public function hasBalancedParentheses(string $input): bool
    {
        $tokenStream = $this->compilerFacade->lexString($input);

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
