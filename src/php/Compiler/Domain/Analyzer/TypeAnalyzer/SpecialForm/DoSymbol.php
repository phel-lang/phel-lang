<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function array_slice;
use function count;

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
            if ($forms->cdr() == null && $stmts === []) {
                // Only one statement?
                $envStmt = $env;
            } elseif ($forms->cdr() == null && $stmts !== []) {
                // Return statement
                $envStmt = $env->isContext(NodeEnvironment::CONTEXT_STATEMENT)
                    ? $env->withStatementContext()
                    : $env->withreturnContext();
            } else {
                // Inner statement
                $envStmt = $env->withStatementContext()->withDisallowRecurFrame();
            }

            $stmts[] = $this->analyzer->analyze($forms->first(), $envStmt);
        }

        // If we don't have any statement, evaluate the nil statement
        if ($stmts === []) {
            $stmts[] = $this->analyzer->analyze(null, $env);
        }

        return new DoNode(
            $env,
            array_slice($stmts, 0, -1),
            $stmts[count($stmts) - 1],
            $list->getStartLocation(),
        );
    }
}
