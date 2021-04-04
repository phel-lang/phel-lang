<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

final class PhpAUnsetSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpArrayUnsetNode
    {
        if ($env->getContext() !== NodeEnvironmentInterface::CONTEXT_STATEMENT) {
            throw AnalyzerException::withLocation("'php/unset can only be called as Statement and not as Expression", $list);
        }

        return new PhpArrayUnsetNode(
            $env,
            $this->analyzer->analyze($list->get(1), $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $this->analyzer->analyze($list->get(2), $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)),
            $list->getStartLocation()
        );
    }
}
