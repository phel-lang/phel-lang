<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Ast\TupleNode;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzeBracketTuple
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironment $env): TupleNode
    {
        $args = [];

        foreach ($tuple as $arg) {
            $envDisallowRecur = $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame();
            $args[] = $this->analyzer->analyze($arg, $envDisallowRecur);
        }

        return new TupleNode($env, $args, $tuple->getStartLocation());
    }
}
