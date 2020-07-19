<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Ast\LocalVarNode;
use Phel\Ast\Node;
use Phel\Ast\PhpVarNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

final class AnalyzeSymbol
{
    use WithAnalyzer;

    public function analyze(Symbol $symbol, NodeEnvironment $env): Node
    {
        if ($symbol->getNamespace() === 'php') {
            return new PhpVarNode($env, $symbol->getName(), $symbol->getStartLocation());
        }

        if ($env->hasLocal($symbol)) {
            return $this->createLocalVarNode($symbol, $env);
        }

        return $this->createGlobalResolve($symbol, $env);
    }

    private function createLocalVarNode(Symbol $symbol, NodeEnvironment $env): LocalVarNode
    {
        $shadowedVar = $env->getShadowed($symbol);

        if ($shadowedVar) {
            $shadowedVar->copyLocationFrom($symbol);

            return new LocalVarNode($env, $shadowedVar, $symbol->getStartLocation());
        }

        return new LocalVarNode($env, $symbol, $symbol->getStartLocation());
    }

    private function createGlobalResolve(Symbol $symbol, NodeEnvironment $env): Node
    {
        $globalResolve = $this->analyzer->getGlobalEnvironment()->resolve($symbol, $env);

        if (!$globalResolve) {
            throw AnalyzerException::withLocation('Can not resolve symbol ' . $symbol->getFullName(), $symbol);
        }

        return $globalResolve;
    }
}
