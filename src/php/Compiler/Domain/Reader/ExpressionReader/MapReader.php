<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel;
use Phel\Compiler\Application\Reader;
use Phel\Compiler\Domain\Parser\ParserNode\ListNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\Map\PersistentMapInterface;

final readonly class MapReader
{
    public function __construct(private Reader $reader)
    {
    }

    public function read(ListNode $node, NodeInterface $root): PersistentMapInterface
    {
        $list = (new ListReader($this->reader))->read($node, $root);

        if ($list->count() % 2 !== 0) {
            throw ReaderException::forNode($node, $root, 'Maps must have an even number of parameters');
        }

        return Phel::map(...$list->toArray())
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
