<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\LetSimplifier;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\DeconstructorInterface;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

use function count;
use function gettype;

/**
 * (let [bindings] body).
 *
 * Introduces local bindings. Bindings are pairs of name and value.
 */
final readonly class LetSymbol implements SpecialFormAnalyzerInterface
{
    public function __construct(
        private AnalyzerInterface $analyzer,
        private DeconstructorInterface $deconstructor,
        private LetSimplifier $letSimplifier = new LetSimplifier(),
    ) {}

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
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
        $newListData[1] = Phel::vector($bindingData);
        $newList = Phel::list($newListData)
            ->copyLocationFrom($list)
            ->withMeta($list->getMeta());

        return $this->analyzeLetOrLoop($newList, $env);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function analyzeLetOrLoop(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        /** @psalm-suppress PossiblyNullArgument */
        /** @var PersistentVectorInterface<mixed> $bindingVector */
        $bindingVector = $list->get(1);
        $bindings = $this->analyzeBindings($bindingVector, $env->withDisallowRecurFrame());

        $locals = [];
        foreach ($bindings as $binding) {
            $locals[] = $binding->getSymbol();
        }

        $bodyEnv = $env->withMergedLocals($locals);
        $bodyEnv = ($env->isContext(NodeEnvironment::CONTEXT_EXPRESSION))
            ? $bodyEnv->withReturnContext()
            : $bodyEnv->withEnvContext($env);

        $shadowPairs = [];
        foreach ($bindings as $binding) {
            $shadowPairs[] = [$binding->getSymbol(), $binding->getShadow()];
        }

        $bodyEnv = $bodyEnv->withLocalsAndShadows($shadowPairs);

        /** @var PersistentListInterface<mixed> $rest1 */
        $rest1 = $list->rest();
        /** @var PersistentListInterface<mixed> $rest2 */
        $rest2 = $rest1->rest();
        $exprs = $rest2->toArray();
        $bodyExpr = $this->analyzer->analyze(
            Phel::list([
                Symbol::create(Symbol::NAME_DO),
                ...$exprs,
            ]),
            $bodyEnv,
        );

        $node = new LetNode(
            $env,
            $bindings,
            $bodyExpr,
            $isLoop = false,
            $list->getStartLocation(),
        );

        return $this->letSimplifier->simplify($node);
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

            $initEnv = $initEnv->withLocalAndShadow($sym, $shadowSym);
        }

        return $nodes;
    }
}
