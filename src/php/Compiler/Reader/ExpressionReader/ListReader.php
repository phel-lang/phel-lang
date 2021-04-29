<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Reader\Reader;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeFactory;

final class ListReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
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
