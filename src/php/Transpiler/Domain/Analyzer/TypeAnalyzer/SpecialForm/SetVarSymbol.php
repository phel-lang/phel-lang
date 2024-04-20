<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;

final class SetVarSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): SetVarNode
    {
        $nameSymbol = $list->get(1);
        if (!($nameSymbol instanceof Symbol)) {
            throw AnalyzerException::withLocation("First argument of 'def must be a Symbol.", $list);
        }

        return new SetVarNode(
            $env,
            $this->analyzer->analyze($nameSymbol, $env->withExpressionContext()),
            $this->analyzer->analyze($list->get(2), $env->withExpressionContext()),
            $list->getStartLocation(),
        );
    }
}
