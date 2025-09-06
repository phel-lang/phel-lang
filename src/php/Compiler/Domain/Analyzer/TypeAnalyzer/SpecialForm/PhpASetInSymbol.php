<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Traversable;

use function assert;

final class PhpASetInSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpArraySetNode
    {
        $keys = $list->get(2);
        assert($keys instanceof Traversable);

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
