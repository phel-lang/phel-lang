<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Generator;
use Phel\Compiler\Parser;
use Phel\Compiler\Parser\ParserNode\QuoteNode;

final class QuoteParser
{
    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function parse(Generator $tokenStream, int $tokenType): QuoteNode
    {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();
        $expression = $this->parser->readExpression($tokenStream);
        $endLocation = $tokenStream->current()->getEndLocation();

        return new QuoteNode($tokenType, $startLocation, $endLocation, $expression);
    }
}
