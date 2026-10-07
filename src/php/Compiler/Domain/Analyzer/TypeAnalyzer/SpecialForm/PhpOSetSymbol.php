<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Shared\Exceptions\ErrorCode;

/**
 * @internal
 */
final class PhpOSetSymbol implements SpecialFormAnalyzerInterface
{
    use AssertsFormArityTrait;
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpObjectSetNode
    {
        $this->assertArityAtLeast($list, 3, '(php/oset (php/-> object property) value)');

        $left = $this->analyzer->analyze($list->get(1), $env->withExpressionContext());
        $right = $this->analyzer->analyze($list->get(2), $env->withExpressionContext());

        if (!$left instanceof PhpObjectCallNode) {
            throw AnalyzerException::withLocation('First argument of php/oget must be a property access', $list, errorCode: ErrorCode::TYPE_ERROR);
        }

        if ($left->isMethodCall()) {
            throw AnalyzerException::withLocation('First argument of php/oget must be a property access', $list, errorCode: ErrorCode::TYPE_ERROR);
        }

        return new PhpObjectSetNode(
            $env,
            $left,
            $right,
            $list->getStartLocation(),
        );
    }
}
