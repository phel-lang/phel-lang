<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Parser\ExpressionParser;

use Phel\Transpiler\Domain\Lexer\TokenStream;
use Phel\Transpiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Transpiler\Domain\Parser\Parser;
use Phel\Transpiler\Domain\Parser\ParserNode\ListNode;

final readonly class ListParser
{
    public function __construct(private Parser $parser)
    {
    }

    /**
     * @throws UnfinishedParserException
     */
    public function parse(TokenStream $tokenStream, int $endTokenType, int $tokenType): ListNode
    {
        $acc = [];
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        while ($tokenStream->valid()) {
            $token = $tokenStream->current();

            if ($token->getType() === $endTokenType) {
                $endLocation = $token->getEndLocation();
                $tokenStream->next();

                return new ListNode($tokenType, $startLocation, $endLocation, $acc);
            }

            $acc[] = $this->parser->readExpression($tokenStream);
        }

        throw UnfinishedParserException::forSnippet($tokenStream->getCodeSnippet(), $tokenStream->current(), 'Unterminated list');
    }
}
