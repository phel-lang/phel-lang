<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Reader\Reader;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class WrapReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function read(QuoteNode $node, string $wrapFn): Tuple
    {
        $expression = $this->reader->readExpression($node->getExpression());

        $tuple = new Tuple([Symbol::create($wrapFn), $expression]);
        $tuple->setStartLocation($node->getStartLocation());
        $tuple->setEndLocation($node->getEndLocation());

        return $tuple;
    }
}
