<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Ast\QuoteNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class QuoteSymbol
{
    public function __invoke(Tuple $tuple, NodeEnvironment $env): QuoteNode
    {
        if (count($tuple) !== 2) {
            throw AnalyzerException::withLocation("Exactly one arguments is required for 'quote", $tuple);
        }

        return new QuoteNode(
            $env,
            $tuple[1],
            $tuple->getStartLocation()
        );
    }
}
