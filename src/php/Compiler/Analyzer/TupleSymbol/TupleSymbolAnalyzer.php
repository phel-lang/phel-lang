<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Ast\Node;
use Phel\Compiler\NodeEnvironment;
use Phel\Lang\Tuple;

interface TupleSymbolAnalyzer
{
    public function analyze(Tuple $tuple, NodeEnvironment $env): Node;
}
