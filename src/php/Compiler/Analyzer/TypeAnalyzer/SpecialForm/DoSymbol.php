<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\DoNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

final class DoSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): DoNode
    {
        if (!($list->get(0) instanceof Symbol && $list->get(0)->getName() === Symbol::NAME_DO)) {
            throw AnalyzerException::withLocation("This is not a 'do.", $list);
        }

        $listCount = count($list);
        $stmts = [];
        for ($i = 1; $i < $listCount - 1; $i++) {
            $stmts[] = $this->analyzer->analyze(
                $list->get($i),
                $env->withContext(NodeEnvironmentInterface::CONTEXT_STATEMENT)->withDisallowRecurFrame()
            );
        }

        return new DoNode(
            $env,
            $stmts,
            $this->ret($list, $env),
            $list->getStartLocation()
        );
    }

    private function ret(PersistentListInterface $list, NodeEnvironmentInterface $env): AbstractNode
    {
        $listCount = count($list);

        if ($listCount > 2) {
            $retEnv = $env->getContext() === NodeEnvironmentInterface::CONTEXT_STATEMENT
                ? $env->withContext(NodeEnvironmentInterface::CONTEXT_STATEMENT)
                : $env->withContext(NodeEnvironmentInterface::CONTEXT_RETURN);

            return $this->analyzer->analyze($list->get($listCount - 1), $retEnv);
        }

        if ($listCount === 2) {
            return $this->analyzer->analyze($list->get($listCount - 1), $env);
        }

        return $this->analyzer->analyze(null, $env);
    }
}
