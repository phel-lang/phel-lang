<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

use function count;

final class PhpNewSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpNewNode
    {
        $listCount = count($list);
        if ($listCount < 2) {
            throw AnalyzerException::withLocation("At least one arguments is required for 'php/new", $list);
        }

        $classExpr = $this->analyzer->analyze(
            $list->get(1),
            $env->withExpressionContext()->withDisallowRecurFrame(),
        );
        $args = [];
        for ($forms = $list->rest()->cdr(); $forms != null; $forms = $forms->cdr()) {
            $args[] = $this->analyzer->analyze(
                $forms->first(),
                $env->withExpressionContext()->withDisallowRecurFrame(),
            );
        }

        return new PhpNewNode(
            $env,
            $classExpr,
            $args,
            $list->getStartLocation(),
        );
    }
}
