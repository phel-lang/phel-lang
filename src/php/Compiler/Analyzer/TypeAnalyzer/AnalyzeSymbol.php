<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;

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

        if ($shadowedVar) {
            $shadowedVar->copyLocationFrom($symbol);

            return new LocalVarNode($env, $shadowedVar, $symbol->getStartLocation());
        }

        return new LocalVarNode($env, $symbol, $symbol->getStartLocation());
    }

    private function createGlobalResolve(Symbol $symbol, NodeEnvironmentInterface $env): AbstractNode
    {
        $globalResolve = $this->analyzer->resolve($symbol, $env);

        if (!$globalResolve) {
            throw AnalyzerException::withLocation("Cannot resolve symbol '{$symbol->getFullName()}'", $symbol);
        }

        return $globalResolve;
    }
}
