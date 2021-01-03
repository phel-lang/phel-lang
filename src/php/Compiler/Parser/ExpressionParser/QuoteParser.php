<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\Parser;
use Phel\Compiler\Parser\ParserNode\QuoteNode;

final class QuoteParser
{
    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function parse(TokenStream $tokenStream, int $tokenType): QuoteNode
    {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();
        $expression = $this->parser->readExpression($tokenStream);
        $endLocation = $tokenStream->current()->getEndLocation();

        return new QuoteNode($tokenType, $startLocation, $endLocation, $expression);
    }
}
