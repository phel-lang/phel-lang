<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\BindingNode;
use Phel\Compiler\Analyzer\Ast\LetNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\DeconstructorInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;

final class LetSymbol implements SpecialFormAnalyzerInterface
{
    private AnalyzerInterface $analyzer;
    private DeconstructorInterface $deconstructor;

    public function __construct(AnalyzerInterface $analyzer, DeconstructorInterface $deconstructor)
    {
        $this->analyzer = $analyzer;
        $this->deconstructor = $deconstructor;
    }

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): LetNode
    {
        if (!($list->get(0) instanceof Symbol && $list->get(0)->getName() === Symbol::NAME_LET)) {
            throw AnalyzerException::withLocation("This is not a 'let.", $list);
        }

        if (count($list) < 2) {
            throw AnalyzerException::withLocation("At least two arguments are required for 'let", $list);
        }

        if (!($list->get(1) instanceof PersistentVectorInterface)) {
            throw AnalyzerException::withLocation('Binding parameter must be a vector', $list);
        }

        if (!(count($list->get(1)) % 2 === 0)) {
            throw AnalyzerException::withLocation('Bindings must be a even number of parameters', $list);
        }

        $bindings = $this->deconstructor->deconstruct($list->get(1));
        $bindingData = [];
        foreach ($bindings as $binding) {
            $bindingData[] = $binding[0];
            $bindingData[] = $binding[1];
        }

        $newListData = $list->toArray();
        $newListData[1] = TypeFactory::getInstance()->persistentVectorFromArray($bindingData);
        $newList = TypeFactory::getInstance()
            ->persistentListFromArray($newListData)
            ->copyLocationFrom($list)
            ->withMeta($list->getMeta());

        return $this->analyzeLetOrLoop($newList, $env);
    }

    private function analyzeLetOrLoop(PersistentListInterface $list, NodeEnvironmentInterface $env): LetNode
    {
        /** @psalm-suppress PossiblyNullArgument */
        /** @var PersistentVectorInterface $bindingVector */
        $bindingVector = $list->get(1);
        $bindings = $this->analyzeBindings($bindingVector, $env->withDisallowRecurFrame());

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

        $exprs = $list->rest()->rest()->toArray();
        $bodyExpr = $this->analyzer->analyze(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_DO), ...$exprs,
            ]),
            $bodyEnv
        );

        return new LetNode(
            $env,
            $bindings,
            $bodyExpr,
            $isLoop = false,
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
