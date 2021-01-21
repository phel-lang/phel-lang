<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Reader\Reader;
use Phel\Lang\Tuple;

final class ListReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function readUsingBrackets(ListNode $node, NodeInterface $root): Tuple
    {
        return $this->readNode($node, true, $root);
    }

    public function read(ListNode $node, NodeInterface $root): Tuple
    {
        return $this->readNode($node, false, $root);
    }

    private function readNode(ListNode $node, bool $isUsingBrackets, NodeInterface $root): Tuple
    {
        $acc = [];
        foreach ($node->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $acc[] = $this->reader->readExpression($child, $root);
        }

        $tuple = new Tuple($acc, $isUsingBrackets);
        $tuple->setStartLocation($node->getStartLocation());
        $tuple->setEndLocation($node->getEndLocation());

        return $tuple;
    }
}
