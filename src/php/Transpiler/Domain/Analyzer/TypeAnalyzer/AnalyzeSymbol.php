<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;

final class AnalyzeSymbol
{
    use WithAnalyzerTrait;

    public function analyze(Symbol $symbol, NodeEnvironmentInterface $env): AbstractNode
    {
        if ($symbol->getNamespace() === 'php') {
            return new PhpVarNode($env, $symbol->getName(), $symbol->getStartLocation());
        }

        if ($env->hasLocal($symbol)) {
            return $this->createLocalVarNode($symbol, $env);
        }

        return $this->createGlobalResolve($symbol, $env);
    }

    private function createLocalVarNode(Symbol $symbol, NodeEnvironmentInterface $env): LocalVarNode
    {
        $shadowedVar = $env->getShadowed($symbol);

        if ($shadowedVar instanceof Symbol) {
            $shadowedVar->copyLocationFrom($symbol);

            return new LocalVarNode($env, $shadowedVar, $symbol->getStartLocation());
        }

        return new LocalVarNode($env, $symbol, $symbol->getStartLocation());
    }

    private function createGlobalResolve(Symbol $symbol, NodeEnvironmentInterface $env): AbstractNode
    {
        $globalResolve = $this->analyzer->resolve($symbol, $env);

        if (!$globalResolve instanceof AbstractNode) {
            throw AnalyzerException::withLocation(sprintf("Cannot resolve symbol '%s'", $symbol->getFullName()), $symbol);
        }

        return $globalResolve;
    }
}
