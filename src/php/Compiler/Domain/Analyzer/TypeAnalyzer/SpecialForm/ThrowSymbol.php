<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

use function count;

final class ThrowSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): ThrowNode
    {
        if (count($list) !== 2) {
            throw AnalyzerException::withLocation("Exact one argument is required for 'throw", $list);
        }

        return new ThrowNode(
            $env,
            $this->analyzer->analyze($list->get(1), $env->withExpressionContext()->withDisallowRecurFrame()),
            $list->getStartLocation(),
        );
    }
}
