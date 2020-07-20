<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Ast\Node;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

interface TupleSymbolAnalyzer
{
    public function analyze(Tuple $tuple, NodeEnvironment $env): Node;
}
