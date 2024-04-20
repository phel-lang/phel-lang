<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;

final class PhpAUnsetSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpArrayUnsetNode
    {
        if (!$env->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            throw AnalyzerException::withLocation("'php/unset can only be called as Statement and not as Expression", $list);
        }

        return new PhpArrayUnsetNode(
            $env,
            $this->analyzer->analyze($list->get(1), $env->withExpressionContext()->withUseGlobalReference(true)),
            $this->analyzer->analyze($list->get(2), $env->withExpressionContext()),
            $list->getStartLocation(),
        );
    }
}
