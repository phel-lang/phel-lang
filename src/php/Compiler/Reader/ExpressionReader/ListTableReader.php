<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Compiler\Reader\Reader;
use Phel\Lang\Table;

final class ListTableReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function read(ListNode $node, NodeInterface $root): Table
    {
        $tuple = (new ListReader($this->reader))->read($node, $root);

        if (!$tuple->hasEvenNumberOfParams()) {
            throw ReaderException::forNode($node, $root, 'Tables must have an even number of parameters');
        }

        return Table::fromTuple($tuple);
    }
}
