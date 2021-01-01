<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TupleSymbol;

use Phel\Compiler\Analyzer\TupleSymbol\Binding\TupleDeconstructorInterface;
use Phel\Compiler\AnalyzerInterface;
use Phel\Compiler\Ast\BindingNode;
use Phel\Compiler\Ast\LetNode;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class LetSymbolInterface implements TupleSymbolAnalyzerInterface
{
    private AnalyzerInterface $analyzer;
    private TupleDeconstructorInterface $tupleDeconstructor;

    public function __construct(AnalyzerInterface $analyzer, TupleDeconstructorInterface $tupleDeconstructor)
    {
        $this->analyzer = $analyzer;
        $this->tupleDeconstructor = $tupleDeconstructor;
    }

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): LetNode
    {
        if (!($tuple[0] instanceof Symbol && $tuple[0]->getName() === Symbol::NAME_LET)) {
            throw AnalyzerException::withLocation("This is not a 'let.", $tuple);
        }

        if (count($tuple) < 2) {
            throw AnalyzerException::withLocation("At least two arguments are required for 'let", $tuple);
        }

        if (!($tuple[1] instanceof Tuple)) {
            throw AnalyzerException::withLocation('Binding parameter must be a tuple', $tuple);
        }

        if (!(count($tuple[1]) % 2 === 0)) {
            throw AnalyzerException::withLocation('Bindings must be a even number of parameters', $tuple);
        }

        $bindings = $this->tupleDeconstructor->deconstruct($tuple[1]);
        $bindingTupleData = [];
        foreach ($bindings as $binding) {
            $bindingTupleData[] = $binding[0];
            $bindingTupleData[] = $binding[1];
        }

        $newTuple = $tuple->update(1, new Tuple($bindingTupleData, true));

        return $this->analyzeLetOrLoop($newTuple, $env);
    }

    private function analyzeLetOrLoop(Tuple $tuple, NodeEnvironmentInterface $env): LetNode
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
                $env->getContext() === NodeEnvironmentInterface::CONTEXT_EXPRESSION
                    ? NodeEnvironmentInterface::CONTEXT_RETURN
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

    /**
     * @return BindingNode[]
     */
    private function analyzeBindings(Tuple $tuple, NodeEnvironmentInterface $env): array
    {
        $tupleCount = count($tuple);
        $initEnv = $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame();
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
