<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;

/**
 * (php/aset arr key value).
 *
 * Sets a value in a PHP array by key.
 *
 * @internal
 */
final class PhpASetSymbol implements SpecialFormAnalyzerInterface
{
    use AssertsFormArityTrait;
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpArraySetNode
    {
        $this->assertArityAtLeast($list, 4, '(php/aset array key value)');

        return new PhpArraySetNode(
            $env,
            $this->analyzer->analyze($list->get(1), $env->withExpressionContext()->withUseGlobalReference(true)),
            [
                $this->analyzer->analyze($list->get(2), $env->withExpressionContext()),
            ],
            $this->analyzer->analyze($list->get(3), $env->withExpressionContext()),
            $list->getStartLocation(),
        );
    }
}
