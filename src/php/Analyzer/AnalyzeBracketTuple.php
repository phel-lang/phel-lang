<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Analyzer;
use Phel\Ast\TupleNode;
use Phel\NodeEnvironment;

final class AnalyzeBracketTuple
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function __invoke($x, NodeEnvironment $env): TupleNode
    {
        $args = [];
        foreach ($x as $arg) {
            $envDisallowRecur = $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame();
            $args[] = $this->analyzer->analyze($arg, $envDisallowRecur);
        }

        return new TupleNode($env, $args, $x->getStartLocation());
    }
}
