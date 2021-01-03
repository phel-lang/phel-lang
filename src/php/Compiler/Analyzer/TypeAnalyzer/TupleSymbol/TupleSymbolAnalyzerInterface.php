<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\Tuple;

interface TupleSymbolAnalyzerInterface
{
    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): AbstractNode;
}
