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

    public function __invoke(Tuple $tuple, NodeEnvironment $env): ThrowNode
    {
        if (count($tuple) !== 2) {
            throw AnalyzerException::withLocation("Exact one argument is required for 'throw", $tuple);
        }

        return new ThrowNode(
            $env,
            $this->analyzer->analyze($tuple[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()),
            $tuple->getStartLocation()
        );
    }
}
