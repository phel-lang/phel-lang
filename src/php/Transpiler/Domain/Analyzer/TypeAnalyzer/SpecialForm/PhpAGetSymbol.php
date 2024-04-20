<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;

final class PhpAGetSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpArrayGetNode
    {
        return new PhpArrayGetNode(
            $env,
            $this->analyzer->analyze($list->get(1), $env->withExpressionContext()),
            $this->analyzer->analyze($list->get(2), $env->withExpressionContext()),
            $list->getStartLocation(),
        );
    }
}
