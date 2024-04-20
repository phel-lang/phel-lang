<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;

final class PhpAPushSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpArrayPushNode
    {
        return new PhpArrayPushNode(
            $env,
            $this->analyzer->analyze($list->get(1), $env->withExpressionContext()->withUseGlobalReference(true)),
            $this->analyzer->analyze($list->get(2), $env->withExpressionContext()),
            $list->getStartLocation(),
        );
    }
}
