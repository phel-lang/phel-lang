<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\ConstantFolder;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

use function count;

/**
 * (if test then else?).
 *
 * Conditional branch: evaluates then if test is truthy, else otherwise.
 */
final class IfSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    /**
     * @param PersistentListInterface<mixed> $list
     */
    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        $this->verifyArguments($list);

        $node = new IfNode(
            $env,
            $this->testExpression($list, $env),
            $this->thenExpression($list, $env),
            $this->elseExpression($list, $env),
            $list->getStartLocation(),
        );

        return new ConstantFolder()->foldIf($node) ?? $node;
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function verifyArguments(PersistentListInterface $list): void
    {
        $listCount = count($list);

        if ($listCount < 3 || $listCount > 4) {
            throw AnalyzerException::withLocation("'if requires two or three arguments", $list);
        }
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function testExpression(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        $envWithDisallowRecurFrame = $env
            ->withExpressionContext()
            ->withDisallowRecurFrame();

        return $this->analyzer->analyze($list->get(1), $envWithDisallowRecurFrame);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function thenExpression(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        return $this->analyzer->analyze($list->get(2), $env);
    }

    /**
     * @param PersistentListInterface<mixed> $list
     */
    private function elseExpression(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        if (count($list) === 3) {
            return $this->analyzer->analyze(null, $env);
        }

        return $this->analyzer->analyze($list->get(3), $env);
    }
}
