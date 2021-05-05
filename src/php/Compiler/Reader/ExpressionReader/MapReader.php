<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Compiler\Reader\Reader;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\TypeFactory;

final class MapReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function read(ListNode $node, NodeInterface $root): PersistentMapInterface
    {
        $list = (new ListReader($this->reader))->read($node, $root);

        if ($list->count() % 2 !== 0) {
            throw ReaderException::forNode($node, $root, 'Maps must have an even number of parameters');
        }

        return TypeFactory::getInstance()
            ->persistentMapFromKVs(...$list->toArray())
            ->setStartLocation($node->getStartLocation())
            ->setEndLocation($node->getEndLocation());
    }
}
