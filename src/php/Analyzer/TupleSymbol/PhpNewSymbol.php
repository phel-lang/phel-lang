<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\PhpNewNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;

final class PhpNewSymbol
{
    use WithAnalyzer;

    public function toNode(Tuple $tuple, NodeEnvironment $env): PhpNewNode
    {
        $tupleCount = count($tuple);
        if ($tupleCount < 2) {
            throw AnalyzerException::withLocation("At least one arguments is required for 'php/new", $tuple);
        }

        $classExpr = $this->analyzer->analyze(
            $tuple[1],
            $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
        );
        $args = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $args[] = $this->analyzer->analyze(
                $tuple[$i],
                $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame()
            );
        }

        return new PhpNewNode(
            $env,
            $classExpr,
            $args,
            $tuple->getStartLocation()
        );
    }
}
