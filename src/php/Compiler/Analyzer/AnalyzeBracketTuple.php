<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer;

use Phel\Compiler\Ast\TupleNode;
use Phel\Compiler\NodeEnvironment;
use Phel\Lang\Tuple;

final class AnalyzeBracketTuple
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironment $env): TupleNode
    {
        $args = [];

        foreach ($tuple as $arg) {
            $envDisallowRecur = $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame();
            $args[] = $this->analyzer->analyze($arg, $envDisallowRecur);
        }

        return new TupleNode($env, $args, $tuple->getStartLocation());
    }
}
