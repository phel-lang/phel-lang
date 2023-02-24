<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

final class PhpASetSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpArraySetNode
    {
        return new PhpArraySetNode(
            $env,
            $this->analyzer->analyze($list->get(1), $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withUseGlobalReference(true)),
            $this->analyzer->analyze($list->get(2), $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $this->analyzer->analyze($list->get(3), $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $list->getStartLocation(),
        );
    }
}
