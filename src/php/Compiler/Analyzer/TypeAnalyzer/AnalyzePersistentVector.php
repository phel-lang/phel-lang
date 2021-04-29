<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Analyzer\Ast\VectorNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
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
            $envDisallowRecur = $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION)->withDisallowRecurFrame();
            $args[] = $this->analyzer->analyze($arg, $envDisallowRecur);
        }

        return new VectorNode($env, $args, $vector->getStartLocation());
    }
}
