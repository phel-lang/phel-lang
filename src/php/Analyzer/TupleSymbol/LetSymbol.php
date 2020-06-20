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

    public function __invoke(Tuple $x, NodeEnvironment $env): LetNode
    {
        if (count($x) < 2) {
            throw new AnalyzerException(
                "At least two arguments are required for 'let",
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!($x[1] instanceof Tuple)) {
            throw new AnalyzerException(
                'Binding parameter must be a tuple',
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        if (!(count($x[1]) % 2 === 0)) {
            throw new AnalyzerException(
                'Bindings must be a even number of parameters',
                $x->getStartLocation(),
                $x->getEndLocation()
            );
        }

        $destructor = new Destructure();
        $bindings = $destructor->run($x[1]);
        $bindingTupleData = [];
        foreach ($bindings as $binding) {
            $bindingTupleData[] = $binding[0];
            $bindingTupleData[] = $binding[1];
        }

        $newTuple = $x->update(1, new Tuple($bindingTupleData, true));

        // TODO: Refactor: get rid of the bool param,because the scope is false always
        return $this->analyzeLetOrLoop($newTuple, $env, false);
    }

    private function analyzeLetOrLoop(Tuple $x, NodeEnvironment $env, bool $isLoop = false): LetNode
    {
        $tupleCount = count($x);
        $exprs = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $exprs[] = $x[$i];
        }

        /** @psalm-suppress PossiblyNullArgument */
        $bindings = $this->analyzeBindings($x[1], $env->withDisallowRecurFrame());

        $locals = [];
        foreach ($bindings as $binding) {
            $locals[] = $binding->getSymbol();
        }

        $recurFrame = new RecurFrame($locals);

        $bodyEnv = $env
            ->withMergedLocals($locals)
            ->withContext(
                $env->getContext() === NodeEnvironment::CTX_EXPR
                    ? NodeEnvironment::CTX_RET
                    : $env->getContext()
            );

        if ($isLoop) {
            $bodyEnv = $bodyEnv->withAddedRecurFrame($recurFrame);
        }

        foreach ($bindings as $binding) {
            $bodyEnv = $bodyEnv->withShadowedLocal($binding->getSymbol(), $binding->getShadow());
        }

        $bodyExpr = $this->analyzer->analyze(Tuple::create(Symbol::create('do'), ...$exprs), $bodyEnv);

        return new LetNode(
            $env,
            $bindings,
            $bodyExpr,
            $isLoop && $recurFrame->isActive(),
            $x->getStartLocation()
        );
    }

    /** @return BindingNode[] */
    private function analyzeBindings(Tuple $x, NodeEnvironment $env): array
    {
        $tupleCount = count($x);
        $initEnv = $env->withContext(NodeEnvironment::CTX_EXPR)->withDisallowRecurFrame();
        $nodes = [];
        for ($i = 0; $i < $tupleCount; $i += 2) {
            $sym = $x[$i];
            if (!($sym instanceof Symbol)) {
                throw new AnalyzerException(
                    'Binding name must be a symbol, got: ' . \gettype($sym),
                    $x->getStartLocation(),
                    $x->getEndLocation()
                );
            }

            $shadowSym = Symbol::gen($sym->getName() . '_')
                ->copyLocationFrom($sym);
            $init = $x[$i + 1];

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
