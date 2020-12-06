<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\WithAnalyzer;
use Phel\Compiler\Ast\PhpNewNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Tuple;

final class PhpNewSymbol implements TupleSymbolAnalyzer
{
    use WithAnalyzer;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): PhpNewNode
    {
        $tupleCount = count($tuple);
        if ($tupleCount < 2) {
            throw AnalyzerException::withLocation("At least one arguments is required for 'php/new", $tuple);
        }

        $classExpr = $this->analyzer->analyze(
            $tuple[1],
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
        );
        $args = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $args[] = $this->analyzer->analyze(
                $tuple[$i],
                $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
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
