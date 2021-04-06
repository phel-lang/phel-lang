<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

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
        $sym = $list->first();
        if (!($sym instanceof Symbol && $sym->getName() === Symbol::NAME_DO)) {
            throw AnalyzerException::withLocation("This is not a 'do.", $list);
        }

        $forms = $list->cdr();
        $stmts = [];
        for (; $forms != null; $forms = $forms->cdr()) {
            if ($forms->cdr() == null && count($stmts) === 0) {
                // Only one statement?
                $envStmt = $env;
            } elseif ($forms->cdr() == null && count($stmts) > 0) {
                // Return statement
                $envStmt = $env->getContext() === NodeEnvironmentInterface::CONTEXT_STATEMENT
                    ? $env->withContext(NodeEnvironmentInterface::CONTEXT_STATEMENT)
                    : $env->withContext(NodeEnvironmentInterface::CONTEXT_RETURN);
            } else {
                // Inner statement
                $envStmt = $env->withContext(NodeEnvironmentInterface::CONTEXT_STATEMENT)->withDisallowRecurFrame();
            }

            $stmts[] = $this->analyzer->analyze($forms->first(), $envStmt);
        }

        // If we don't have any statement, evaluate the nil statement
        if (count($stmts) === 0) {
            $stmts[] = $this->analyzer->analyze(null, $env);
        }

        return new DoNode(
            $env,
            array_slice($stmts, 0, -1),
            $stmts[count($stmts) - 1],
            $list->getStartLocation()
        );
    }
}
