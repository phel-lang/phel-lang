<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Application\Parser;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\ParserNode\QuoteNode;

final readonly class QuoteParser
{
    public function __construct(private Parser $parser)
    {
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
