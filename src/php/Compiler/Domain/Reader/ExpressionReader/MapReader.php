<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Shared\Parser\Node\ListNode;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

use function count;

/**
 * @internal
 */
final readonly class MapReader
{
    public function __construct(private ReaderInterface $reader) {}

    /**
     * @return PersistentMapInterface<mixed, mixed>
     */
    public function read(ListNode $node, NodeInterface $root): PersistentMapInterface
    {
        $values = [];
        $keys = [];
        $keyNodes = [];
        foreach ($node->getChildren() as $child) {
            if ($child instanceof TriviaNodeInterface) {
                continue;
            }

            $value = $this->reader->readExpression($child, $root);
            if (count($values) % 2 === 0) {
                $keys[] = $value;
                $keyNodes[] = $child;
            }

            $values[] = $value;
        }

        if (count($values) % 2 !== 0) {
            throw ReaderException::forNode($node, $root, 'Maps must have an even number of parameters');
        }

        $map = Phel::map(...$values);
        DuplicateKeyGuard::assertUnique($keys, $keyNodes, $map->count(), $root);

        return $map
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
