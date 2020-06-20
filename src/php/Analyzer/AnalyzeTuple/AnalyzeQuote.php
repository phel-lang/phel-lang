<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Ast\QuoteNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzeQuote
{
    public function __invoke(Tuple $x, NodeEnvironment $env): QuoteNode
    {
        if (count($x) !== 2) {
            throw new AnalyzerException(
                "Exactly one arguments is required for 'quote",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        return new QuoteNode(
            $env,
            $x[1],
            $x->getStartLocation()
        );
    }
}
