<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Ast\Node;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

interface TupleSymbolToNode
{
    public function toNode(Tuple $tuple, NodeEnvironment $env): Node;
}
