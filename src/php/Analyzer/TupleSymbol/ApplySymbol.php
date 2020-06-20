<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\ApplyNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class ApplySymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $env): ApplyNode
    {
        $tupleCount = count($x);
        if ($tupleCount < 3) {
            throw new AnalyzerException(
                "At least three arguments are required for 'apply",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $fn = $this->analyzer->analyze($x[1], $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());

        $args = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $args[] = $this->analyzer->analyze(
                $x[$i],
                $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
            );
        }

        return new ApplyNode(
            $env,
            $fn,
            $args,
            $x->getStartLocation()
        );
    }
}
