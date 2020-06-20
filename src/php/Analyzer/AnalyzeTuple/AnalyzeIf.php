<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\IfNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzeIf
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $env): IfNode
    {
        $tupleCount = count($x);
        if ($tupleCount < 3 || $tupleCount > 4) {
            throw new AnalyzerException(
                "'if requires two or three arguments",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $testExpr = $this->analyzer->analyze(
            $x[1],
            $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
        );
        $thenExpr = $this->analyzer->analyze($x[2], $env);
        if ($tupleCount === 3) {
            $elseExpr = $this->analyzer->analyze(null, $env);
        } else {
            $elseExpr = $this->analyzer->analyze($x[3], $env);
        }

        return new IfNode(
            $env,
            $testExpr,
            $thenExpr,
            $elseExpr,
            $x->getStartLocation()
        );
    }
}
