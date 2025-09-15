<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\TypeInterface;

final class AnalyzePersistentSet
{
    use WithAnalyzerTrait;

    public function analyze(PersistentHashSetInterface $set, NodeEnvironmentInterface $env): SetNode
    {
        $values = [];

        /** @var bool|float|int|string|TypeInterface|null $value */
        foreach ($set->getIterator() as $value) {
            $envDisallowRecur = $env->withExpressionContext()->withDisallowRecurFrame();
            $values[] = $this->analyzer->analyze($value, $envDisallowRecur);
        }

        return new SetNode($env, $values, $set->getStartLocation());
    }
}
