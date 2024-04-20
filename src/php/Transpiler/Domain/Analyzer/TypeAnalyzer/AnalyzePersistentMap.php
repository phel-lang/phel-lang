<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\MapNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class AnalyzePersistentMap
{
    use WithAnalyzerTrait;

    public function analyze(PersistentMapInterface $map, NodeEnvironmentInterface $env): MapNode
    {
        $keyValues = [];
        $kvEnv = $env->withExpressionContext();

        /** @var TypeInterface|string|float|int|bool|null $value */
        foreach ($map as $key => $value) {
            /** @var TypeInterface|string|float|int|bool|null $key */
            $keyValues[] = $this->analyzer->analyze($key, $kvEnv);
            $keyValues[] = $this->analyzer->analyze($value, $kvEnv);
        }

        return new MapNode($env, $keyValues, $map->getStartLocation());
    }
}
