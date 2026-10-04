<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;

/**
 * (set! var value).
 *
 * Mutates a previously defined global variable.
 *
 * @internal
 */
final class SetVarSymbol implements SpecialFormAnalyzerInterface
{
    use AssertsFormArityTrait;
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): SetVarNode
    {
        $this->assertArityAtLeast($list, 3, '(set-var name value)');

        $nameSymbol = $list->get(1);
        if (!($nameSymbol instanceof Symbol)) {
            throw AnalyzerException::wrongArgumentType("First argument of 'def", 'Symbol', $nameSymbol, $list);
        }

        $target = $this->analyzer->analyze($nameSymbol, $env->withExpressionContext());
        if (!$target instanceof GlobalVarNode) {
            throw AnalyzerException::withLocation(
                $nameSymbol->getFullName() . ' is not a var: binding and with-redefs rebind a var defined with def',
                $nameSymbol,
                errorCode: ErrorCode::BINDING_ERROR,
            );
        }

        return new SetVarNode(
            $env,
            $target,
            $this->analyzer->analyze($list->get(2), $env->withExpressionContext()),
            $list->getStartLocation(),
        );
    }
}
