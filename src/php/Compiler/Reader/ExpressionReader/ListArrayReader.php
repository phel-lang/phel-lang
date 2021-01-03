<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Reader\Reader;
use Phel\Lang\PhelArray;

final class ListArrayReader
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    public function read(ListNode $node): PhelArray
    {
        $tuple = (new ListReader($this->reader))->read($node);

        return PhelArray::fromTuple($tuple);
    }
}
