<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\MetaInterface;

/**
 * Extracts a `MetaInterface` value's reader-attached metadata map and analyses
 * it as a `MapNode`. Returns `null` when the host has no meta (or an empty
 * meta map), which is the common case.
 *
 * Used by literal analysers (vector / map / set) so emitters can preserve
 * `^{:k v} […]` metadata round-trip through the compile pipeline.
 */
final class LiteralMetaAnalyzer
{
    public static function analyze(
        AnalyzerInterface $analyzer,
        MetaInterface $host,
        NodeEnvironmentInterface $env,
    ): ?MapNode {
        $meta = $host->getMeta();
        if (!$meta instanceof PersistentMapInterface || $meta->count() === 0) {
            return null;
        }

        $analyzed = $analyzer->analyze($meta, $env->withExpressionContext());
        return $analyzed instanceof MapNode ? $analyzed : null;
    }
}
