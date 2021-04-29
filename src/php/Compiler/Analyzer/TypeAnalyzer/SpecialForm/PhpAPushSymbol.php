<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

final class PhpAPushSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpArrayPushNode
    {
        return new PhpArrayPushNode(
            $env,
            $this->analyzer->analyze($list->get(1), $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $this->analyzer->analyze($list->get(2), $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $list->getStartLocation()
        );
    }
}
