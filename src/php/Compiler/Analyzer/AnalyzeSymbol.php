<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer;

use Phel\Compiler\Ast\LocalVarNode;
use Phel\Compiler\Ast\Node;
use Phel\Compiler\Ast\PhpVarNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;

final class AnalyzeSymbol
{
    use WithAnalyzer;

    public function analyze(Symbol $symbol, NodeEnvironmentInterface $env): Node
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

        if ($shadowedVar) {
            $shadowedVar->copyLocationFrom($symbol);

            return new LocalVarNode($env, $shadowedVar, $symbol->getStartLocation());
        }

        return new LocalVarNode($env, $symbol, $symbol->getStartLocation());
    }

    private function createGlobalResolve(Symbol $symbol, NodeEnvironmentInterface $env): Node
    {
        $globalResolve = $this->analyzer->resolve($symbol, $env);

        if (!$globalResolve) {
            throw AnalyzerException::withLocation("Can not resolve symbol '{$symbol->getFullName()}'", $symbol);
        }

        return $globalResolve;
    }
}
