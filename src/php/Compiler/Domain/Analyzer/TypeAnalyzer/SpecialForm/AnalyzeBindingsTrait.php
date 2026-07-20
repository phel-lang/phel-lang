<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Symbol;

use function count;
use function gettype;

/**
 * Shared binding-vector analysis for `let` and `loop`: each `[sym init]`
 * pair becomes a {@see BindingNode} with a gensym'd shadow, and every init
 * is analyzed in an environment that already sees the previous bindings.
 * Composing analyzers must provide the private `$analyzer`
 * ({@see \Phel\Compiler\Domain\Analyzer\AnalyzerInterface}) property.
 */
trait AnalyzeBindingsTrait
{
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
