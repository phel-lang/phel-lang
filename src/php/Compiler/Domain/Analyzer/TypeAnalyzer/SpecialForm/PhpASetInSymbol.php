<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Traversable;

/**
 * (php/aset-in arr key1 key2 ... value).
 *
 * Sets a nested value in a PHP array.
 *
 * @internal
 */
final class PhpASetInSymbol implements SpecialFormAnalyzerInterface
{
    use AssertsFormArityTrait;
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpArraySetNode
    {
        $this->assertArityAtLeast($list, 4, '(php/aset-in array [key ...] value)');

        $keys = $list->get(2);
        if (!$keys instanceof Traversable) {
            // An assertion is a statement about Phel's own code. The shape of
            // a user's form is not one, and `assert()` is compiled out under
            // `zend.assertions=-1`, so the same source failed two ways (#3297).
            throw AnalyzerException::wrongArity($list, '(php/aset-in array [key ...] value)');
        }

        $accessExprs = [];
        foreach ($keys as $k) {
            $accessExprs[] = $this->analyzer->analyze($k, $env->withExpressionContext());
        }

        return new PhpArraySetNode(
            $env,
            $this->analyzer->analyze($list->get(1), $env->withExpressionContext()->withUseGlobalReference(true)),
            $accessExprs,
            $this->analyzer->analyze($list->get(3), $env->withExpressionContext()),
            $list->getStartLocation(),
        );
    }
}
