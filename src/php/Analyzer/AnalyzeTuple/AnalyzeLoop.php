<?php

declare(strict_types=1);

namespace Phel\Analyzer\AnalyzeTuple;

use Phel\Analyzer\WithAnalyzer;
use Phel\Ast\BindingNode;
use Phel\Ast\LetNode;
use Phel\Destructure;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;
use Phel\NodeEnvironment;
use Phel\RecurFrame;

final class AnalyzeLoop
{
    use WithAnalyzer;

    public function __invoke(Tuple $x, NodeEnvironment $env): LetNode
    {
        $tupleCount = count($x);
        if (!($x[0] instanceof Symbol && $x[0] == 'loop')) {
            throw new AnalyzerException(
                "This is not a 'loop.",
                $x->getStartLocation(),
                $x->getEndLocation(),
            );
        }

        if ($tupleCount < 2) {
            throw new AnalyzerException(
                "At least two arguments are required for 'loop.",
                $x->getStartLocation(),
                $x->getEndLocation(),
            );
        }

        if (!($x[1] instanceof Tuple)) {
            throw new AnalyzerException(
                'Binding parameter must be a tuple.',
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

        $loopBindings = $x[1];
        $loopBindingsCount = count($loopBindings);

        $preInits = [];
        $lets = [];
        for ($i = 0; $i < $loopBindingsCount; $i += 2) {
            $b = $loopBindings[$i];
            $init = $loopBindings[$i + 1];

            Destructure::assertSupportedBinding($b);

            if ($b instanceof Symbol) {
                $preInits[] = $b;
                $preInits[] = $init;
            } else {
                $tempSym = Symbol::gen();
                $tempSym->setStartLocation($b->getStartLocation());
                $tempSym->setEndLocation($b->getEndLocation());

                $preInits[] = $tempSym;
                $preInits[] = $init;
                $lets[] = $b;
                $lets[] = $tempSym;
            }
        }

        if (count($lets) > 0) {
            $bodyExpr = [];
            for ($i = 2; $i < $tupleCount; $i++) {
                $bodyExpr[] = $x[$i];
            }
            $letSym = new Symbol('let');
            $letSym->setStartLocation($x[0]->getStartLocation());
            $letSym->setEndLocation($x[0]->getEndLocation());

            $letExpr = Tuple::create(
                $letSym,
                new Tuple($lets, true),
                ...$bodyExpr
            );
            $letExpr->setStartLocation($x->getStartLocation());
            $letExpr->setEndLocation($x->getEndLocation());

            $newExpr = Tuple::create(
                $x[0],
                new Tuple($preInits, true),
                $letExpr
            );
            $newExpr->setStartLocation($x->getStartLocation());
            $newExpr->setEndLocation($x->getEndLocation());

            return $this->analyzeLetOrLoop($newExpr, $env, true);
        }

        // TODO: Get rid of the bool argument
        return $this->analyzeLetOrLoop($x, $env, true);
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

        $bodyExpr = $this->analyzer->analyze(Tuple::create(new Symbol('do'), ...$exprs), $bodyEnv);

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
