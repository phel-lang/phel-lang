<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\TypeAnalyzer;

use Phel\Compiler\Analyzer\Ast\MapNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;
use Phel\Lang\TypeInterface;

final class AnalyzeMap
{
    use WithAnalyzerTrait;

    public function analyze(PersistentHashMapInterface $map, NodeEnvironmentInterface $env): MapNode
    {
        $keyValues = [];
        $kvEnv = $env->withContext(NodeEnvironmentInterface::CONTEXT_EXPRESSION);

        /** @var TypeInterface|string|float|int|bool|null $value */
        foreach ($map as $key => $value) {
            /** @var TypeInterface|string|float|int|bool|null $key */
            $keyValues[] = $this->analyzer->analyze($key, $kvEnv);
            $keyValues[] = $this->analyzer->analyze($value, $kvEnv);
        }

        return new MapNode($env, $keyValues, $map->getStartLocation());
    }
}
