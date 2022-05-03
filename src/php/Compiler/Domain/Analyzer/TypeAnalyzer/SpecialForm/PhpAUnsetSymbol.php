<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
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
