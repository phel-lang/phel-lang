<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurFrame;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidator;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

use function count;
use function gettype;

/**
 * (loop [bindings] body).
 *
 * Loop with initial bindings; use recur to iterate.
 */
final readonly class LoopSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private BindingValidator $bindingValidator,
    ) {}

    /**
     * @param PersistentListInterface<mixed> $list
     */
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

        if ($lets !== []) {
            /** @var PersistentListInterface<mixed> $rest1 */
            $rest1 = $list->rest();
            /** @var PersistentListInterface<mixed> $rest2 */
            $rest2 = $rest1->rest();
            $bodyExpr = $rest2->toArray();
            $letSym = Symbol::create(Symbol::NAME_LET)->copyLocationFrom($list->get(0));
            $letExpr = Phel::list([
                $letSym,
                Phel::vector($lets),
                ...$bodyExpr,
            ])->copyLocationFrom($list);
            $newExpr = Phel::list([
                $list->get(0),
                Phel::vector($preInits),
                $letExpr,
            ])->copyLocationFrom($list);

            return $this->analyzeLetOrLoop($newExpr, $env);
        }

        return $this->analyzeLetOrLoop($list, $env);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function analyzeLetOrLoop(PersistentListInterface $list, NodeEnvironmentInterface $env): LetNode
    {
        /** @var PersistentListInterface<mixed> $rest1 */
        $rest1 = $list->rest();
        /** @var PersistentListInterface<mixed> $rest2 */
        $rest2 = $rest1->rest();
        $exprs = $rest2->toArray();

        /** @psalm-suppress PossiblyNullArgument */
        /** @var PersistentVectorInterface<mixed> $bindingVector */
        $bindingVector = $list->get(1);
        $bindings = $this->analyzeBindings($bindingVector, $env->withDisallowRecurFrame());

        $locals = [];
        $shadows = [];
        foreach ($bindings as $binding) {
            $locals[] = $binding->getSymbol();
            $shadows[] = $binding->getShadow();
        }

        // `recur` must assign to the loop's own binding variables (the shadow
        // names), not whatever inner `let` happens to re-shadow the same name
        // at the recur site — otherwise a shadowing `let` makes the loop
        // binding never update and the loop spins forever.
        $recurFrame = new RecurFrame($locals, $shadows);

        $bodyEnv = $env->withMergedLocals($locals);
        $bodyEnv = ($env->isContext(NodeEnvironment::CONTEXT_EXPRESSION))
            ? $bodyEnv->withReturnContext()
            : $bodyEnv->withEnvContext($env);

        $bodyEnv = $bodyEnv->withAddedRecurFrame($recurFrame);

        foreach ($bindings as $binding) {
            $bodyEnv = $bodyEnv->withShadowedLocal($binding->getSymbol(), $binding->getShadow());
        }

        $bodyExpr = $this->analyzer->analyze(
            Phel::list([
                Symbol::create(Symbol::NAME_DO),
                ...$exprs,
            ]),
            $bodyEnv,
        );

        return new LetNode(
            $env,
            $bindings,
            $bodyExpr,
            $recurFrame->isActive(),
            $list->getStartLocation(),
        );
    }

    /**
     * @param PersistentVectorInterface<mixed> $vector
     *
     * @return list<BindingNode>
     */
    private function analyzeBindings(PersistentVectorInterface $vector, NodeEnvironmentInterface $env): array
    {
        $vectorCount = count($vector);
        $initEnv = $env->withExpressionContext()->withDisallowRecurFrame();
        $nodes = [];
        for ($i = 0; $i < $vectorCount; $i += 2) {
            $sym = $vector->get($i);
            if (!($sym instanceof Symbol)) {
                throw AnalyzerException::withLocation('Binding name must be a symbol, got: ' . gettype($sym), $vector);
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
                $sym->getStartLocation(),
            );

            $initEnv = $initEnv->withMergedLocals([$sym])->withShadowedLocal($sym, $shadowSym);
        }

        return $nodes;
    }
}
