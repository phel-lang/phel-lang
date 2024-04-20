<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;

final class PhpOSetSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpObjectSetNode
    {
        $left = $this->analyzer->analyze($list->get(1), $env->withExpressionContext());
        $right = $this->analyzer->analyze($list->get(2), $env->withExpressionContext());

        if (!$left instanceof PhpObjectCallNode) {
            throw AnalyzerException::withLocation('First argument of php/oget must be a property access', $list);
        }

        if ($left->isMethodCall()) {
            throw AnalyzerException::withLocation('First argument of php/oget must be a property access', $list);
        }

        return new PhpObjectSetNode(
            $env,
            $left,
            $right,
            $list->getStartLocation(),
        );
    }
}
