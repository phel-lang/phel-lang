<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\WithAnalyzerTrait;
use Phel\Compiler\Ast\DoNode;
use Phel\Compiler\Ast\AbstractNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class DoSymbol implements TupleSymbolAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): DoNode
    {
        if (!($tuple[0] instanceof Symbol && $tuple[0]->getName() === Symbol::NAME_DO)) {
            throw AnalyzerException::withLocation("This is not a 'do.", $tuple);
        }

        $tupleCount = count($tuple);
        $stmts = [];
        for ($i = 1; $i < $tupleCount - 1; $i++) {
            $stmts[] = $this->analyzer->analyze(
                $tuple[$i],
                $env->withContext(NodeEnvironmentInterface::CONTEXT_STATEMENT)->withDisallowRecurFrame()
            );
        }

        return new DoNode(
            $env,
            $stmts,
            $this->ret($tuple, $env),
            $tuple->getStartLocation()
        );
    }

    private function ret(Tuple $tuple, NodeEnvironmentInterface $env): AbstractNode
    {
        $tupleCount = count($tuple);

        if ($tupleCount > 2) {
            $retEnv = $env->getContext() === NodeEnvironmentInterface::CONTEXT_STATEMENT
                ? $env->withContext(NodeEnvironmentInterface::CONTEXT_STATEMENT)
                : $env->withContext(NodeEnvironmentInterface::CONTEXT_RETURN);

            return $this->analyzer->analyze($tuple[$tupleCount - 1], $retEnv);
        }

        if ($tupleCount === 2) {
            return $this->analyzer->analyze($tuple[$tupleCount - 1], $env);
        }

        return $this->analyzer->analyze(null, $env);
    }
}
