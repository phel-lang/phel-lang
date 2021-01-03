<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\Parser\ExpressionParser;

use Phel\Compiler\Parser\Parser;
use Phel\Compiler\Parser\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\TokenStream;

final class MetaParser
{
    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function parse(TokenStream $tokenStream): MetaNode
    {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        $meta =  $this->parser->readExpression($tokenStream);
        $children = [];
        do {
            $object =  $this->parser->readExpression($tokenStream);
            $children[] = $object;
        } while ($object instanceof TriviaNodeInterface);

        $endLocation = $tokenStream->current()->getEndLocation();

        return new MetaNode($meta, $startLocation, $endLocation, $children);
    }
}
