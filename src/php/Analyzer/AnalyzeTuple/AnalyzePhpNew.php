<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\PhpNewNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class AnalyzePhpNew
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $env): PhpNewNode
    {
        $tupleCount = count($x);
        if ($tupleCount < 2) {
            throw new AnalyzerException(
                "At least one arguments is required for 'php/new",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $classExpr = $this->analyzer->analyze($x[1],
            $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        $args = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $args[] = $this->analyzer->analyze($x[$i],
                $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame());
        }

        return new PhpNewNode(
            $env,
            $classExpr,
            $args,
            $x->getStartLocation()
        );
    }

}
