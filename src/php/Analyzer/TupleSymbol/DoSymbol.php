<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\DoNode;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class DoSymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $tuple, NodeEnvironment $env): DoNode
    {
        $tupleCount = count($tuple);
        $stmts = [];
        for ($i = 1; $i < $tupleCount - 1; $i++) {
            $stmts[] = $this->analyzer->analyze(
                $tuple[$i],
                $env->withContext(NodeEnvironment::CTX_STMT)->withDisallowRecurFrame()
            );
        }

        if ($tupleCount > 2) {
            $retEnv = $env->getContext() === NodeEnvironment::CTX_STMT
                ? $env->withContext(NodeEnvironment::CTX_STMT)
                : $env->withContext(NodeEnvironment::CTX_RET);
            $ret = $this->analyzer->analyze($tuple[$tupleCount - 1], $retEnv);
        } elseif ($tupleCount === 2) {
            $ret = $this->analyzer->analyze($tuple[$tupleCount - 1], $env);
        } else {
            $ret = $this->analyzer->analyze(null, $env);
        }

        return new DoNode(
            $env,
            $stmts,
            $ret,
            $tuple->getStartLocation()
        );
    }
}
