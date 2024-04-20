<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Parser\ExpressionParser;

use Phel\Transpiler\Domain\Lexer\TokenStream;
use Phel\Transpiler\Domain\Parser\Parser;
use Phel\Transpiler\Domain\Parser\ParserNode\MetaNode;
use Phel\Transpiler\Domain\Parser\ParserNode\TriviaNodeInterface;

final readonly class MetaParser
{
    public function __construct(private Parser $parser)
    {
    }

    public function parse(TokenStream $tokenStream): MetaNode
    {
        $startLocation = $tokenStream->current()->getStartLocation();
        $tokenStream->next();

        $meta = $this->parser->readExpression($tokenStream);
        $children = [];
        do {
            $object = $this->parser->readExpression($tokenStream);
            $children[] = $object;
        } while ($object instanceof TriviaNodeInterface);

        $endLocation = $tokenStream->current()->getEndLocation();

        return new MetaNode($meta, $startLocation, $endLocation, $children);
    }
}
