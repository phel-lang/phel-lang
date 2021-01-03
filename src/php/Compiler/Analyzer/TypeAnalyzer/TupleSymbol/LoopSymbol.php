<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol;

use Phel\Compiler\Analyzer\TypeAnalyzer\TupleSymbol\Binding\BindingValidator;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\BindingNode;
use Phel\Compiler\Analyzer\Ast\LetNode;
use Phel\Compiler\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\Tuple;

final class LoopSymbol implements TupleSymbolAnalyzerInterface
{
    private AnalyzerInterface $analyzer;
    private BindingValidator $bindingValidator;

    public function __construct(AnalyzerInterface $analyzer, BindingValidator $bindingValidator)
    {
        $this->analyzer = $analyzer;
        $this->bindingValidator = $bindingValidator;
    }

    public function analyze(Tuple $tuple, NodeEnvironmentInterface $env): LetNode
    {
        if (!($tuple[0] instanceof Symbol && $tuple[0]->getName() === Symbol::NAME_LOOP)) {
            throw AnalyzerException::withLocation("This is not a 'loop.", $tuple);
        }

        $tupleCount = count($tuple);
        if ($tupleCount < 2) {
            throw AnalyzerException::withLocation("At least two arguments are required for 'loop.", $tuple);
        }

        if (!($tuple[1] instanceof Tuple)) {
            throw AnalyzerException::withLocation('Binding parameter must be a tuple.', $tuple);
        }

        if (!(count($tuple[1]) % 2 === 0)) {
            throw AnalyzerException::withLocation('Bindings must be a even number of parameters', $tuple);
        }

        $loopBindings = $tuple[1];
        $loopBindingsCount = count($loopBindings);

        $preInits = [];
        $lets = [];
        for ($i = 0; $i < $loopBindingsCount; $i += 2) {
            $b = $loopBindings[$i];
            $init = $loopBindings[$i + 1];

            $this->bindingValidator->assertSupportedBinding($b);

            if ($b instanceof Symbol) {
                $preInits[] = $b;
                $preInits[] = $init;
            } else {
                $tempSym = Symbol::gen()->copyLocationFrom($b);
                $preInits[] = $tempSym;
                $preInits[] = $init;
                $lets[] = $b;
                $lets[] = $tempSym;
            }
        }

        if (count($lets) > 0) {
            $bodyExpr = [];
            for ($i = 2; $i < $tupleCount; $i++) {
                $bodyExpr[] = $tuple[$i];
            }
            $letSym = Symbol::create(Symbol::NAME_LET)->copyLocationFrom($tuple[0]);
            $letExpr = (Tuple::create($letSym, new Tuple($lets, true), ...$bodyExpr))->copyLocationFrom($tuple);
            $newExpr = (Tuple::create($tuple[0], new Tuple($preInits, true), $letExpr))->copyLocationFrom($tuple);

            return $this->analyzeLetOrLoop($newExpr, $env);
        }

        return $this->analyzeLetOrLoop($tuple, $env);
    }

    private function analyzeLetOrLoop(Tuple $tuple, NodeEnvironmentInterface $env): LetNode
    {
        $tupleCount = count($tuple);
        $exprs = [];
        for ($i = 2; $i < $tupleCount; $i++) {
            $exprs[] = $tuple[$i];
        }

        /** @psalm-suppress PossiblyNullArgument */
        $bindings = $this->analyzeBindings($tuple[1], $env->withDisallowRecurFrame());

        $locals = [];
        foreach ($bindings as $binding) {
            $locals[] = $binding->getSymbol();
        }

        $recurFrame = new RecurFrame($locals);

        $bodyEnv = $env
            ->withMergedLocals($locals)
            ->withContext(
                $env->getContext() === NodeEnvironmentInterface::CONTEXT_EXPRESSION
                    ? NodeEnvironmentInterface::CONTEXT_RETURN
                    : $env->getContext()
            );

        $bodyEnv = $bodyEnv->withAddedRecurFrame($recurFrame);

        foreach ($bindings as $binding) {
            $bodyEnv = $bodyEnv->withShadowedLocal($binding->getSymbol(), $binding->getShadow());
        }

        $bodyExpr = $this->analyzer->analyze(Tuple::create(Symbol::create(Symbol::NAME_DO), ...$exprs), $bodyEnv);

        return new LetNode(
            $env,
            $bindings,
            $bodyExpr,
            $recurFrame->isActive(),
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
