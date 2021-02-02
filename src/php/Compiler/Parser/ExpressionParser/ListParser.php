<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Parser\Parser;
use Phel\Compiler\Parser\ParserNode\ListNode;

final class ListParser
{
    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
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
