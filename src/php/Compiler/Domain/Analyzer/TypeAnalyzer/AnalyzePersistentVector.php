<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\TypeInterface;

final class AnalyzePersistentVector
{
    use WithAnalyzerTrait;

    /**
     * @param PersistentVectorInterface<mixed> $vector
     */
    public function analyze(PersistentVectorInterface $vector, NodeEnvironmentInterface $env): VectorNode
    {
        $args = [];

        /** @var bool|float|int|string|TypeInterface|null $arg */
        foreach ($vector->getIterator() as $arg) {
            $envDisallowRecur = $env->withExpressionContext()->withDisallowRecurFrame();
            $args[] = $this->analyzer->analyze($arg, $envDisallowRecur);
        }

        $meta = LiteralMetaAnalyzer::analyze($this->analyzer, $vector, $env);

        return new VectorNode($env, $args, $vector->getStartLocation(), $meta);
    }
}
