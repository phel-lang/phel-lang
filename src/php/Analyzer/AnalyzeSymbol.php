<?php

declare(strict_types=1);

namespace Phel\Analyzer;

use Phel\Analyzer;
use Phel\Ast\LocalVarNode;
use Phel\Ast\Node;
use Phel\Ast\PhpVarNode;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\NodeEnvironment;

final class AnalyzeSymbol
{
    private Analyzer $analyzer;

    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    public function __invoke(Symbol $x, NodeEnvironment $env): Node
    {
        if (strpos($x->getName(), 'php/') === 0) {
            return new PhpVarNode($env, substr($x->getName(), 4), $x->getStartLocation());
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

        throw new AnalyzerException('Can not resolve symbol ' . $x->getName(), $x->getStartLocation(), $x->getEndLocation());
    }
}
