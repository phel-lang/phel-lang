<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\ThrowNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class ThrowSymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $env): ThrowNode
    {
        if (count($x) !== 2) {
            throw new AnalyzerException(
                "Exact one argument is required for 'throw",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        return new ThrowNode(
            $env,
            $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()),
            $x->getStartLocation()
        );
    }
}
