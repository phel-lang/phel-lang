<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\IfNode;
use Phel\Ast\Node;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class IfSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironment $env): IfNode
    {
        $tupleCount = count($tuple);
        if ($tupleCount < 3 || $tupleCount > 4) {
            throw AnalyzerException::withLocation("'if requires two or three arguments", $tuple);
        }

        $testExpr = $this->analyzer->analyze(
            $tuple[1],
            $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
        );
        $thenExpr = $this->analyzer->analyze($tuple[2], $env);
        $elseExpr = $this->elseExpr($tuple, $env);

        return new IfNode(
            $env,
            $testExpr,
            $thenExpr,
            $elseExpr,
            $tuple->getStartLocation()
        );
    }

    private function elseExpr(Tuple $tuple, NodeEnvironment $env): Node
    {
        if (count($tuple) === 3) {
            return $this->analyzer->analyze(null, $env);
        }

        return $this->analyzer->analyze($tuple[3], $env);
    }
}
