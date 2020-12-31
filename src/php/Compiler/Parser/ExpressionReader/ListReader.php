<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Reader;
use Phel\Lang\Tuple;

final class ListReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function readUsingBrackets(ListNode $node): Tuple
    {
        return $this->readNode($node, true);
    }

    public function read(ListNode $node): Tuple
    {
        return $this->readNode($node, false);
    }

    private function readNode(ListNode $node, bool $isUsingBrackets): Tuple
    {
        $acc = [];
        foreach ($node->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $acc[] = $this->reader->readExpression($child);
        }

        $tuple = new Tuple($acc, $isUsingBrackets);
        $tuple->setStartLocation($node->getStartLocation());
        $tuple->setEndLocation($node->getEndLocation());

        return $tuple;
    }
}
