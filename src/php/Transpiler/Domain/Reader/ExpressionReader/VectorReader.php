<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Reader\ExpressionReader;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\TypeFactory;
use Phel\Transpiler\Domain\Parser\ParserNode\ListNode;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Transpiler\Domain\Reader\Reader;

final readonly class VectorReader
{
    public function __construct(private Reader $reader)
    {
    }

    public function read(ListNode $node, NodeInterface $root): PersistentVectorInterface
    {
        $acc = [];
        foreach ($node->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $acc[] = $this->reader->readExpression($child, $root);
        }

        return TypeFactory::getInstance()
            ->persistentVectorFromArray($acc)
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
