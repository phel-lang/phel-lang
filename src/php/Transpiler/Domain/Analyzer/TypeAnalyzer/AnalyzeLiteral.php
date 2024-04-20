<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;

final class AnalyzeLiteral
{
    public function analyze(float|bool|int|string|array|TypeInterface|null $value, NodeEnvironmentInterface $env): LiteralNode
    {
        $sourceLocation = ($value instanceof TypeInterface)
            ? $value->getStartLocation()
            : null;

        return new LiteralNode($env, $value, $sourceLocation);
    }
}
