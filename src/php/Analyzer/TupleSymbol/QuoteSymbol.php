<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Ast\QuoteNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class QuoteSymbol implements TupleToNode
{
    public function toNode(Tuple $tuple, NodeEnvironment $env): QuoteNode
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
