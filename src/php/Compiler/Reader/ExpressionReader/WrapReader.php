<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\QuoteNode;
use Phel\Compiler\Reader\Reader;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

final class WrapReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function read(QuoteNode $node, string $wrapFn, NodeInterface $root): PersistentListInterface
    {
        $expression = $this->reader->readExpression($node->getExpression(), $root);

        return TypeFactory::getInstance()
            ->persistentListFromArray([Symbol::create($wrapFn), $expression])
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
