<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel;
use Phel\Compiler\Application\Reader;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\QuoteNode;

final readonly class WrapReader
{
    public function __construct(private Reader $reader) {}

    /**
     * @return PersistentListInterface<mixed>
     */
    public function read(QuoteNode $node, string $wrapFn, NodeInterface $root): PersistentListInterface
    {
        $expression = $this->reader->readExpression($node->getExpression(), $root);

        return Phel::list([Symbol::create($wrapFn), $expression])
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
