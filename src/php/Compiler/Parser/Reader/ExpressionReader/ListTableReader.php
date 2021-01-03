<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\Reader\ExpressionReader;

use Phel\Compiler\Parser\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\Reader;
use Phel\Exceptions\ReaderException;
use Phel\Lang\Table;

final class ListTableReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function read(ListNode $node): Table
    {
        $tuple = (new ListReader($this->reader))->read($node);

        if (!$tuple->hasEvenNumberOfParams()) {
            throw ReaderException::forNode($node, 'Tables must have an even number of parameters');
        }

        return Table::fromTuple($tuple);
    }
}
