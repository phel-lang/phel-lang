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

    public function analyze(PersistentVectorInterface $vector, NodeEnvironmentInterface $env): VectorNode
    {
        $args = [];

        /** @var TypeInterface|string|float|int|bool|null $arg */
        foreach ($vector->getIterator() as $arg) {
            $envDisallowRecur = $env->withExpressionContext()->withDisallowRecurFrame();
            $args[] = $this->analyzer->analyze($arg, $envDisallowRecur);
        }

        return new VectorNode($env, $args, $vector->getStartLocation());
    }
}
