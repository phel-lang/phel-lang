<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\ThrowNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;

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
