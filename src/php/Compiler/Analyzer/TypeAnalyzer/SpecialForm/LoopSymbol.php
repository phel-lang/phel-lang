<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\BindingNode;
use Phel\Compiler\Analyzer\Ast\LetNode;
use Phel\Compiler\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

final class LoopSymbol implements SpecialFormAnalyzerInterface
{
    private AnalyzerInterface $analyzer;
    private BindingValidator $bindingValidator;

    public function __construct(AnalyzerInterface $analyzer, BindingValidator $bindingValidator)
    {
        $this->analyzer = $analyzer;
        $this->bindingValidator = $bindingValidator;
    }

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): LetNode
    {
        if (!($list->get(0) instanceof Symbol && $list->get(0)->getName() === Symbol::NAME_LOOP)) {
            throw AnalyzerException::withLocation("This is not a 'loop.", $list);
        }

        $listCount = count($list);
        if ($listCount < 2) {
            throw AnalyzerException::withLocation("At least two arguments are required for 'loop.", $list);
        }

        if (!($list->get(1) instanceof PersistentVectorInterface)) {
            throw AnalyzerException::withLocation('Binding parameter must be a vector.', $list);
        }

        if (!(count($list->get(1)) % 2 === 0)) {
            throw AnalyzerException::withLocation('Bindings must be a even number of parameters', $list);
        }

        /** @var PersistentVectorInterface $loopBindings */
        $loopBindings = $list->get(1);
        $loopBindingsCount = count($loopBindings);

        $preInits = [];
        $lets = [];
        for ($i = 0; $i < $loopBindingsCount; $i += 2) {
            $b = $loopBindings->get($i);
            $init = $loopBindings->get($i + 1);

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
            $bodyExpr = $list->rest()->rest()->toArray();
            $letSym = Symbol::create(Symbol::NAME_LET)->copyLocationFrom($list->get(0));
            $letExpr = TypeFactory::getInstance()->persistentListFromArray([
                $letSym,
                TypeFactory::getInstance()->persistentVectorFromArray($lets),
                ...$bodyExpr,
            ])->copyLocationFrom($list);
            $newExpr = TypeFactory::getInstance()->persistentListFromArray([
                $list->get(0),
                TypeFactory::getInstance()->persistentVectorFromArray($preInits),
                $letExpr,
            ])->copyLocationFrom($list);

            return $this->analyzeLetOrLoop($newExpr, $env);
        }

        return $this->analyzeLetOrLoop($list, $env);
    }

    private function analyzeLetOrLoop(PersistentListInterface $list, NodeEnvironmentInterface $env): LetNode
    {
        $listCount = count($list);
        $exprs = $list->rest()->rest()->toArray();

        /** @psalm-suppress PossiblyNullArgument */
        /** @var PersistentVectorInterface $bindingVector */
        $bindingVector = $list->get(1);
        $bindings = $this->analyzeBindings($bindingVector, $env->withDisallowRecurFrame());

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

        $bodyExpr = $this->analyzer->analyze(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_DO),
                ...$exprs,
            ]),
            $bodyEnv
        );

        return new LetNode(
            $env,
            $bindings,
            $bodyExpr,
            $recurFrame->isActive(),
            $list->getStartLocation()
        );
    }

    /**
     * @return BindingNode[]
     */
    private function analyzeBindings(PersistentVectorInterface $vector, NodeEnvironmentInterface $env): array
    {
        $vectorCount = count($vector);
        $initEnv = $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame();
        $nodes = [];
        for ($i = 0; $i < $vectorCount; $i += 2) {
            $sym = $vector->get($i);
            if (!($sym instanceof Symbol)) {
                throw AnalyzerException::withLocation('Binding name must be a symbol, got: ' . \gettype($sym), $vector);
            }

            $shadowSym = Symbol::gen($sym->getName() . '_')->copyLocationFrom($sym);
            $init = $vector->get($i + 1);

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
