<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

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
