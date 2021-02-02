<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Tuple;

final class PhpNewSymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

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
