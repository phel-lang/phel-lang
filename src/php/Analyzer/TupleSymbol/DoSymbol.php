<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\DoNode;
use Phel\Ast\Node;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class DoSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironment $env): DoNode
    {
        if (!($tuple[0] instanceof Symbol && $tuple[0]->getName() === Symbol::NAME_DO)) {
            throw AnalyzerException::withLocation("This is not a 'do.", $tuple);
        }

        $tupleCount = count($tuple);
        $stmts = [];
        for ($i = 1; $i < $tupleCount - 1; $i++) {
            $stmts[] = $this->analyzer->analyze(
                $tuple[$i],
                $env->withContext(NodeEnvironment::CTX_STMT)->withDisallowRecurFrame()
            );
        }

        return new DoNode(
            $env,
            $stmts,
            $this->ret($tuple, $env),
            $tuple->getStartLocation()
        );
    }

    private function ret(Tuple $tuple, NodeEnvironment $env): Node
    {
        $tupleCount = count($tuple);

        if ($tupleCount > 2) {
            $retEnv = $env->getContext() === NodeEnvironment::CTX_STMT
                ? $env->withContext(NodeEnvironment::CTX_STMT)
                : $env->withContext(NodeEnvironment::CTX_RET);

            return $this->analyzer->analyze($tuple[$tupleCount - 1], $retEnv);
        }

        if ($tupleCount === 2) {
            return $this->analyzer->analyze($tuple[$tupleCount - 1], $env);
        }

        return $this->analyzer->analyze(null, $env);
    }
}
