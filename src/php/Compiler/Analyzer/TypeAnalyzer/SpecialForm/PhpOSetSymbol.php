<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

final class PhpOSetSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpObjectSetNode
    {
        $left = $this->analyzer->analyze($list->get(1), $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION));
        $right = $this->analyzer->analyze($list->get(2), $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION));

        if (!$left instanceof PhpObjectCallNode) {
            throw AnalyzerException::withLocation('First argument of php/oget must be a property access', $list);
        }

        if ($left->isMethodCall() === true) {
            throw AnalyzerException::withLocation('First argument of php/oget must be a property access', $list);
        }

        return new PhpObjectSetNode(
            $env,
            $left,
            $right,
            $list->getStartLocation()
        );
    }
}
