<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel\Compiler\Application\Reader;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeFactory;

final readonly class ListReader
{
    public function __construct(private Reader $reader)
    {
    }

    public function read(ListNode $node, NodeInterface $root): PersistentListInterface
    {
        $acc = [];
        foreach ($node->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $acc[] = $this->reader->readExpression($child, $root);
        }

        return TypeFactory::getInstance()
            ->persistentListFromArray($acc)
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
