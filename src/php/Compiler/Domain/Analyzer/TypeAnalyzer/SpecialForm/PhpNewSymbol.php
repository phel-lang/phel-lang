<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\WithAnalyzerTrait;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;

use function count;
use function preg_match;

/**
 * (php/new ClassName args...).
 *
 * Creates a new PHP object instance.
 */
final class PhpNewSymbol implements SpecialFormAnalyzerInterface
{
    use WithAnalyzerTrait;

    public function analyze(PersistentListInterface $list, NodeEnvironmentInterface $env): PhpNewNode
    {
        $listCount = count($list);
        if ($listCount < 2) {
            throw AnalyzerException::withLocation("At least one arguments is required for 'php/new", $list);
        }

        $classEnv = $env->withExpressionContext()->withDisallowRecurFrame();
        $classExpr = $this->analyzeClassExpr($list->get(1), $classEnv);
        $args = [];
        for ($forms = $list->rest()->cdr(); $forms !== null; $forms = $forms->cdr()) {
            $args[] = $this->analyzer->analyze(
                $forms->first(),
                $classEnv,
            );
        }

        return new PhpNewNode(
            $env,
            $classExpr,
            $args,
            $list->getStartLocation(),
        );
    }

    private function analyzeClassExpr(mixed $classExpr, NodeEnvironmentInterface $env): AbstractNode
    {
        if (!$classExpr instanceof Symbol || $env->hasLocal($classExpr)) {
            return $this->analyzer->analyze($classExpr, $env);
        }

        $resolvedClassExpr = $this->analyzer->resolve($classExpr, $env);
        if ($resolvedClassExpr instanceof AbstractNode) {
            return $resolvedClassExpr;
        }

        if ($classExpr->getNamespace() !== null || !$this->looksLikeRootPhpClassName($classExpr->getName())) {
            return $this->analyzer->analyze($classExpr, $env);
        }

        $fqn = Symbol::create('\\' . $classExpr->getName());
        $fqn->copyLocationFrom($classExpr);

        return new PhpClassNameNode($env, $fqn, $classExpr->getStartLocation());
    }

    private function looksLikeRootPhpClassName(string $name): bool
    {
        return preg_match('/^[A-Za-z_]\w*$/', $name) === 1;
    }
}
