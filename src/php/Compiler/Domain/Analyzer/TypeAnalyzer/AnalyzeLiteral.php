<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer;

use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\TypeInterface;

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
