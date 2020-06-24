<?php

declare(strict_types=1);

namespace Phel\Analyzer\TupleSymbol;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\BindingNode;
use Phel\Ast\LetNode;
use Phel\Destructure;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use Phel\RecurFrame;

final class LetSymbol
{
    use WithAnalyzer;

    public function __invoke(Tuple $tuple, NodeEnvironment $env): LetNode
    {
        if (count($tuple) < 2) {
            throw AnalyzerException::withLocation("At least two arguments are required for 'let", $tuple);
        }

        if (!($tuple[1] instanceof Tuple)) {
            throw AnalyzerException::withLocation('Binding parameter must be a tuple', $tuple);
        }

        if (!(count($tuple[1]) % 2 === 0)) {
            throw AnalyzerException::withLocation('Bindings must be a even number of parameters', $tuple);
        }

        $destructor = new Destructure();
        $bindings = $destructor->run($tuple[1]);
        $bindingTupleData = [];
        foreach ($bindings as $binding) {
            $bindingTupleData[] = $binding[0];
            $bindingTupleData[] = $binding[1];
        }

        $newTuple = $tuple->update(1, new Tuple($bindingTupleData, true));

        return $this->analyzeLetOrLoop($newTuple, $env);
    }

    private function analyzeLetOrLoop(Tuple $tuple, NodeEnvironment $env): LetNode
    {
        $exprs = [];
        for ($i = 2, $iMax = count($tuple); $i < $iMax; $i++) {
            $exprs[] = $tuple[$i];
        }

        /** @psalm-suppress PossiblyNullArgument */
        $bindings = $this->analyzeBindings($tuple[1], $env->withDisallowRecurFrame());

        $locals = [];
        foreach ($bindings as $binding) {
            $locals[] = $binding->getSymbol();
        }

        $bodyEnv = $env
            ->withMergedLocals($locals)
            ->withContext(
                $env->getContext() === NodeEnvironment::CTX_EXPR
                    ? NodeEnvironment::CTX_RET
                    : $env->getContext()
            );

        foreach ($bindings as $binding) {
            $bodyEnv = $bodyEnv->withShadowedLocal($binding->getSymbol(), $binding->getShadow());
        }

        $bodyExpr = $this->analyzer->analyze(Tuple::create(Symbol::create(Symbol::NAME_DO), ...$exprs), $bodyEnv);

        return new LetNode(
            $env,
            $bindings,
            $bodyExpr,
            $isLoop = false,
            $tuple->getStartLocation()
        );
    }

    /** @return BindingNode[] */
    private function analyzeBindings(Tuple $tuple, NodeEnvironment $env): array
    {
        $tupleCount = count($tuple);
        $initEnv = $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame();
        $nodes = [];
        for ($i = 0; $i < $tupleCount; $i += 2) {
            $sym = $tuple[$i];
            if (!($sym instanceof Symbol)) {
                throw AnalyzerException::withLocation('Binding name must be a symbol, got: ' . \gettype($sym), $tuple);
            }

            $shadowSym = Symbol::gen($sym->getName() . '_')->copyLocationFrom($sym);
            $init = $tuple[$i + 1];

            $nextBoundTo = $initEnv->getBoundTo() . '.' . $sym->getName();
            $expr = $this->analyzer->analyze($init, $initEnv->withBoundTo($nextBoundTo));

            $nodes[] = new BindingNode(
                $env,
                $sym,
                $shadowSym,
                $expr,
                $sym->getStartLocation()
            );

            $initEnv = $initEnv->withMergedLocals([$sym])->withShadowedLocal($sym, $shadowSym);
        }

        return $nodes;
    }
}
