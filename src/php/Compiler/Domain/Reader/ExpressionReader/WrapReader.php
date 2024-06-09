<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel\Compiler\Application\Reader;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\QuoteNode;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

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
