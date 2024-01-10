<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\ApplyNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\TypeInterface;

use function count;

final class ApplySymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): ApplyNode
    {
        if (count($list) < 3) {
            throw AnalyzerException::withLocation("At least three arguments are required for 'apply", $list);
        }

        $data = $list->rest();
        $fnExpr = $data->first();
        $args = $data->rest();

        return new ApplyNode(
            $env,
            $this->fnExpr($fnExpr, $env),
            $this->arguments($args, $env),
            $list->getStartLocation(),
        );
    }

    /**
     * Analyze the function expression of the apply special form.
     */
    private function fnExpr(float|bool|int|string|TypeInterface|null $x, NodeEnvironmentInterface $env): AbstractNode
    {
        return $this->analyzer->analyze(
            $x,
            $env->withExpressionContext()->withDisallowRecurFrame(),
        );
    }

    private function arguments(PersistentListInterface $argsList, NodeEnvironmentInterface $env): array
    {
        $args = [];

        foreach ($argsList as $element) {
            $args[] = $this->fnExpr($element, $env);
        }

        return $args;
    }
}
