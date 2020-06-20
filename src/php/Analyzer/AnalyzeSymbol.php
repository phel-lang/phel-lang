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

    public function __invoke(Symbol $x, NodeEnvironment $env): Node
    {
        if ($x->getNamespace() && $x->getNamespace() === 'php') {
            return new PhpVarNode($env, $x->getName(), $x->getStartLocation());
        }

        if ($env->hasLocal($x)) {
            $shadowedVar = $env->getShadowed($x);
            if ($shadowedVar) {
                $shadowedVar->setStartLocation($x->getStartLocation());
                $shadowedVar->setEndLocation($x->getEndLocation());

                return new LocalVarNode($env, $shadowedVar, $x->getStartLocation());
            }

            return new LocalVarNode($env, $x, $x->getStartLocation());
        }

        $globalResolve = $this->analyzer->getGlobalEnvironment()->resolve($x, $env);
        if ($globalResolve) {
            return $globalResolve;
        }

        throw new AnalyzerException('Can not resolve symbol ' . $x->getFullName(), $x->getStartLocation(), $x->getEndLocation());
    }
}
