<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Generator;
use Phel\Compiler\Parser;
use Phel\Compiler\Parser\ParserNode\MetaNode;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;

final class MetaParser
{
    private Parser $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function parse(Generator $tokenStream): MetaNode
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
