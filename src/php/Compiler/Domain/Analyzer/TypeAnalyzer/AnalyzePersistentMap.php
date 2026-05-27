<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\TypeInterface;

final class AnalyzePersistentMap
{
    use WithAnalyzerTrait;

    /**
     * @param PersistentMapInterface<mixed, mixed> $map
     */
    public function analyze(PersistentMapInterface $map, NodeEnvironmentInterface $env): MapNode
    {
        $keyValues = [];
        $kvEnv = $env->withExpressionContext();

        /** @var bool|float|int|string|TypeInterface|null $value */
        foreach ($map as $key => $value) {
            /** @var bool|float|int|string|TypeInterface|null $key */
            $keyValues[] = $this->analyzer->analyze($key, $kvEnv);
            $keyValues[] = $this->analyzer->analyze($value, $kvEnv);
        }

        // Pass the env (not the kv env) so the meta map is analyzed in
        // the outer context. `null` if the literal carries no `^{…}` meta.
        $meta = LiteralMetaAnalyzer::analyze($this->analyzer, $map, $env);

        return new MapNode($env, $keyValues, $map->getStartLocation(), $meta);
    }
}
