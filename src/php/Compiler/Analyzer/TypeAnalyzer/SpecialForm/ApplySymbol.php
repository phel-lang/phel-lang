<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeInterface;

final class ApplySymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): ApplyNode
    {
        if (count($list) < 3) {
            throw AnalyzerException::withLocation("At least three arguments are required for 'apply", $list);
        }

        return new ApplyNode(
            $env,
            $this->fnExpr($list->get(1), $env),
            $this->arguments($list, $env),
            $list->getStartLocation()
        );
    }

    /**
     * Analyze the function expression of the apply special form.
     *
     * @param TypeInterface|string|float|int|bool|null $x
     * @param NodeEnvironmentInterface $env
     *
     * @return AbstractNode
     */
    private function fnExpr($x, NodeEnvironmentInterface $env): AbstractNode
    {
        return $this->analyzer->analyze(
            $x,
            $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame()
        );
    }

    private function arguments(PersistentListInterface $x, NodeEnvironmentInterface $env): array
    {
        $args = [];
        for ($i = 2, $iMax = count($x); $i < $iMax; $i++) {
            $args[] = $this->fnExpr($x->get($i), $env);
        }

        return $args;
    }
}
