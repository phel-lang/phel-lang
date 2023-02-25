<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

use function count;

/**
 * (if test then else?).
 */
final class IfSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): IfNode
    {
        $this->verifyArguments($list);

        return new IfNode(
            $env,
            $this->testExpression($list, $env),
            $this->thenExpression($list, $env),
            $this->elseExpression($list, $env),
            $list->getStartLocation(),
        );
    }

    private function verifyArguments(PersistentListInterface $list): void
    {
        $listCount = count($list);

        if ($listCount < 3 || $listCount > 4) {
            throw AnalyzerException::withLocation("'if requires two or three arguments", $list);
        }
    }

    private function testExpression(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        $envWithDisallowRecurFrame = $env
            ->withExpressionContext()
            ->withDisallowRecurFrame();

        return $this->analyzer->analyze($list->get(1), $envWithDisallowRecurFrame);
    }

    private function thenExpression(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        return $this->analyzer->analyze($list->get(2), $env);
    }

    private function elseExpression(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        if (count($list) === 3) {
            return $this->analyzer->analyze(null, $env);
        }

        return $this->analyzer->analyze($list->get(3), $env);
    }
}
