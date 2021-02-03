<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class QuoteSymbol implements TupleSymbolAnalyzerInterface
{
    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): QuoteNode
    {
        if (!($tuple[0] instanceof Symbol && $tuple[0]->getName() === Symbol::NAME_QUOTE)) {
            throw AnalyzerException::withLocation("This is not a 'quote.", $tuple);
        }

        if (count($tuple) !== 2) {
            throw AnalyzerException::withLocation("Exactly one argument is required for 'quote", $tuple);
        }

        return new QuoteNode(
            $env,
            $tuple[1],
            $tuple->getStartLocation()
        );
    }
}
