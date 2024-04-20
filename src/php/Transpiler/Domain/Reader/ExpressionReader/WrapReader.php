<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Reader\ExpressionReader;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\Domain\Parser\ParserNode\QuoteNode;
use Phel\Transpiler\Domain\Reader\Reader;

final readonly class WrapReader
{
    public function __construct(private Reader $reader)
    {
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
