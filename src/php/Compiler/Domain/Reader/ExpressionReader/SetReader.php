<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

/**
 * @internal
 */
final readonly class SetReader
{
    public function __construct(private ReaderInterface $reader) {}

    /**
     * @return PersistentHashSetInterface<mixed>
     */
    public function read(ListNode $node, NodeInterface $root): PersistentHashSetInterface
    {
        $acc = [];
        $elementNodes = [];
        foreach ($node->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $acc[] = $this->reader->readExpression($child, $root);
            $elementNodes[] = $child;
        }

        $set = Phel::set($acc);
        DuplicateKeyGuard::assertUnique($acc, $elementNodes, $set->count(), $root);

        return $set
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
