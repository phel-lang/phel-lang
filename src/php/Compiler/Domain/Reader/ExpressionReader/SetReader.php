<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel;
use Phel\Compiler\Application\Reader;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

final readonly class SetReader
{
    public function __construct(private Reader $reader) {}

    /**
     * @return PersistentHashSetInterface<mixed>
     */
    public function read(ListNode $node, NodeInterface $root): PersistentHashSetInterface
    {
        $acc = [];
        foreach ($node->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $acc[] = $this->reader->readExpression($child, $root);
        }

        return Phel::set($acc)
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
